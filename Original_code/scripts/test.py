import cv2
import dlib
import numpy as np
import pickle
import sys
import os
import time
import requests
from keras_facenet import FaceNet
from deepface import DeepFace
from flask import Flask, request, jsonify


app = Flask(__name__)

HAAR_PATH = "resources/haar_face.xml"  
PREDICTOR_PATH = "shape_predictor/shape_predictor_68_face_landmarks.dat"  
DATASET_DIR = r"C:\TechNest_Rec_Dataset"
SAMPLES_REQUIRED = 7



os.makedirs(DATASET_DIR, exist_ok=True)

# Load models
haar_cascade = cv2.CascadeClassifier(cv2.samples.findFile(HAAR_PATH))
predictor = dlib.shape_predictor(PREDICTOR_PATH)
print("Predictor loaded successfully")
embedder = FaceNet()



def bring_window_to_front(winname):
    cv2.namedWindow(winname, cv2.WINDOW_NORMAL)
    cv2.setWindowProperty(winname, cv2.WND_PROP_TOPMOST, 1)


def configure_camera(cap, calibration_time=2.0):
   
    try:
        cap.set(cv2.CAP_PROP_AUTOFOCUS, 1)
    except Exception:
        pass

    start = time.time()
    while time.time() - start < calibration_time:
        ret, _ = cap.read()
        if not ret:
            break
        cv2.waitKey(1)
   
    return


def detect_face(frame):
    gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
    faces = haar_cascade.detectMultiScale(gray, scaleFactor=1.3, minNeighbors=6, minSize=(120, 120))
    return max(faces, key=lambda b: b[2] * b[3]) if len(faces) > 0 else None


def get_embedding(frame, box):
    x, y, w, h = box
    face_crop = frame[y:y+h, x:x+w]
    if face_crop.size == 0:
        return None
    face_crop = cv2.resize(face_crop, (160, 160))
    return embedder.embeddings([face_crop])[0]


def save_user(name, embedding):
    user_dir = os.path.join(DATASET_DIR, name)
    os.makedirs(user_dir, exist_ok=True)
    pkl_path = os.path.join(user_dir, f"{name}_embedding.pkl")
    with open(pkl_path, "wb") as f:
        pickle.dump(embedding, f)
    return True


def fetch_users():
    users = []
    if not os.path.exists(DATASET_DIR):
        return users
    for user in os.listdir(DATASET_DIR):
        user_dir = os.path.join(DATASET_DIR, user)
        if os.path.isdir(user_dir):
            for f in os.listdir(user_dir):
                if f.endswith("_embedding.pkl"):
                    with open(os.path.join(user_dir, f), "rb") as file:
                        emb = pickle.load(file)
                        users.append((user, emb))
    return users


def recognize_face(embedding, threshold=0.8):
    users = fetch_users()
    min_dist, identity = float("inf"), "Unknown"
    for name, db_emb in users:
        try:
            dist = np.linalg.norm(embedding - db_emb)
        except Exception:
            continue
        if dist < min_dist:
            min_dist, identity = dist, name
    return (identity, min_dist) if min_dist < threshold else ("Unknown", min_dist)



def _capture_samples_from_camera(samples_required=SAMPLES_REQUIRED, sample_delay=1.0, window_name="Register"):
    cap = cv2.VideoCapture(0)
    if not cap.isOpened():
        return {"status": "error", "message": "Unable to open camera", "samples": []}

    configure_camera(cap)
    samples = []
    count = 0
    try:
        while count < samples_required:
            ret, frame = cap.read()
            if not ret:
                break
            box = detect_face(frame)
            if box is not None:
                emb = get_embedding(frame, box)
                if emb is not None:
                    samples.append(emb)
                    count += 1
                    # display feedback
                    x, y, w, h = box
                    cv2.rectangle(frame, (x, y), (x + w, y + h), (0, 255, 0), 2)
                    cv2.putText(frame, f"Captured {count}/{samples_required}", (x, y - 10),
                                cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)
                    bring_window_to_front(window_name)
                    cv2.imshow(window_name, frame)
                    cv2.waitKey(1)
                    time.sleep(sample_delay)
            else:
                # show frame to the user
                cv2.imshow(window_name, frame)
                bring_window_to_front(window_name)
                if cv2.waitKey(1) & 0xFF == 27:
                    break
        cv2.destroyAllWindows()
    finally:
        cap.release()

    return {"status": "ok", "samples": samples}


def register_user_core(name: str, samples_required=SAMPLES_REQUIRED):
    if not name or not name.strip():
        return {"status": "error", "message": "Missing name"}

    print(f"[Register] Preparing to capture {samples_required} samples for '{name}'")
    capture_result = _capture_samples_from_camera(samples_required)
    samples = capture_result.get("samples", [])
    if len(samples) >= samples_required:
        avg_emb = np.mean(samples, axis=0)
        save_user(name, avg_emb)
        return {"status": "success", "message": f"User {name} registered", "samples_captured": len(samples)}
    else:
        return {"status": "error", "message": "Insufficient samples", "samples_captured": len(samples)}


def reenroll_user_core(username: str, samples_required=SAMPLES_REQUIRED):
    if not username or not username.strip():
        return {"status": "error", "message": "Missing name"}

    user_dir = os.path.join(DATASET_DIR, username)
    if os.path.exists(user_dir):
        for f in os.listdir(user_dir):
            try:
                os.remove(os.path.join(user_dir, f))
            except Exception:
                pass

    # call register core
    return register_user_core(username, samples_required=samples_required)


def login_user_core(username: str, required_successes=SAMPLES_REQUIRED, threshold=0.8):
    if not username or not username.strip():
        return {"status": "error", "message": "Missing name"}

    cap = cv2.VideoCapture(0)
    if not cap.isOpened():
        return {"status": "error", "message": "Unable to open camera"}

    configure_camera(cap)
    count = 0
    try:
        while count < required_successes:
            ret, frame = cap.read()
            if not ret:
                break
            box = detect_face(frame)
            if box is not None:
                emb = get_embedding(frame, box)
                if emb is not None:
                    identity, dist = recognize_face(emb, threshold)
                    if identity == username:
                        count += 1
                        x, y, w, h = box
                        cv2.rectangle(frame, (x, y), (x + w, y + h), (0, 255, 0), 2)
                        cv2.putText(frame, f"Match {count}/{required_successes}", (x, y - 10),
                                    cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)
                        bring_window_to_front("Login")
                        cv2.imshow("Login", frame)
                        cv2.waitKey(1)
                        time.sleep(1)
                    else:
                      
                        bring_window_to_front("Login")
                        cv2.imshow("Login", frame)
                        if cv2.waitKey(1) & 0xFF == 27:
                            break
            else:
                bring_window_to_front("Login")
                cv2.imshow("Login", frame)
                if cv2.waitKey(1) & 0xFF == 27:
                    break
        cv2.destroyAllWindows()
    finally:
        cap.release()

    if count >= required_successes:
        return {"status": "success", "message": f"User {username} verified", "matches": count}
    else:
        return {"status": "error", "message": "Login failed", "matches": count}


def logout_user_core(username: str):
    if not username:
        return {"status": "error", "message": "Missing name"}
  
    return {"status": "success", "message": f"User {username} logged out"}



@app.route("/register", methods=["POST"])
def register_user_route():
    name = request.form.get("name")
    if name is None:
        return jsonify({"status": "error", "message": "Missing name"}), 400
    result = register_user_core(name)
    status_code = 200 if result.get("status") == "success" else 400
    return jsonify(result), status_code


@app.route("/reenroll", methods=["POST"])
def reenroll_user_route():
    username = request.form.get("name")
    if not username:
        return jsonify({"status": "error", "message": "Missing name"}), 400
    result = reenroll_user_core(username)
    status_code = 200 if result.get("status") == "success" else 400
    return jsonify(result), status_code


@app.route("/login", methods=["POST"])
def login_user_route():
    username = request.form.get("name")
    if not username:
        return jsonify({"status": "error", "message": "Missing name"}), 400
    result = login_user_core(username)
    status_code = 200 if result.get("status") == "success" else 400
    return jsonify(result), status_code


@app.route("/logout", methods=["POST"])
def logout_user_route():
    username = request.form.get("name", "")
    result = logout_user_core(username)
    status_code = 200 if result.get("status") == "success" else 400
    return jsonify(result), status_code



def test_webcam_cli():
    test_cap = cv2.VideoCapture(0)
    if not test_cap.isOpened():
        print("[Webcam Test] Unable to open camera.")
        return
    configure_camera(test_cap)
    print("[Webcam Test] Press ESC to exit test mode.")
    start = time.time()
    while True:
        ret, frame = test_cap.read()
        if not ret:
            break
        box = detect_face(frame)
        if box is not None:
            x, y, w, h = box
            cv2.rectangle(frame, (x, y), (x + w, y + h), (0, 255, 0), 2)
            cv2.putText(frame, "Face Detected", (x, y - 10),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)
        cv2.imshow("Webcam Test", frame)
        bring_window_to_front("Webcam Test")
        key = cv2.waitKey(1) & 0xFF
        if key == 27:
            break
    test_cap.release()
    cv2.destroyAllWindows()


def cli_menu():
    print("--------------------------------------------------------------------------------")
    print("=== TechNest Recognition - Information/Attendance System for facial scanning===")
    print("Think and choose your option wisely!")
    print("1. Register User (New)")
    print("2. Login User")
    print("3. Re-enroll User (Existing)")
    print("4. Logout User (Exit TechNest Room)")
    print("5. Test Webcam")
    choice = input("Select option: ").strip()

    if choice == "1":
        name = input("Enter name to register: ").strip()
        result = register_user_core(name)
        print(result)
    elif choice == "2":
        name = input("Enter name to login: ").strip()
        result = login_user_core(name)
        print(result)
    elif choice == "3":
        name = input("Enter name to reenroll (overwrite): ").strip()
        result = reenroll_user_core(name)
        print(result)
    elif choice == "4":
        name = input("Enter name to logout: ").strip()
        result = logout_user_core(name)
        print(result)
    elif choice == "5":
        test_webcam_cli()
    else:
        print("Invalid choice.")



if __name__ == "__main__":
    try:
        cli_menu()
    except KeyboardInterrupt:
        print("\nExiting...")
        try:
            cv2.destroyAllWindows()
        except Exception:
            pass
        sys.exit(0)
