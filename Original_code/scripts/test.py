import cv2
import dlib
import numpy as np
import pickle
import sys
import os
import time
import requests
from keras_facenet import FaceNet
from flask import Flask, request, jsonify


app = Flask(__name__)
BASE_DIR = os.path.dirname(os.path.abspath(__file__)) 
DATASET_DIR = os.path.join(BASE_DIR, "dataset") 
os.makedirs(DATASET_DIR, exist_ok=True)

HAAR_PATH = "resources/haar_face.xml"
PREDICTOR_PATH = "shape_predictor/shape_predictor_68_face_landmarks.dat"
SAMPLES_REQUIRED = 7

# PHP backend URL (once ma implement mo na rei)
PHP_API_URL = "http://localhost/techt_recog_api"

os.makedirs(DATASET_DIR, exist_ok=True)


# MODEL LOADING(for checking kay gaguba kis a mag load sakon)
haar_cascade = cv2.CascadeClassifier(cv2.samples.findFile(HAAR_PATH))
predictor = dlib.shape_predictor(PREDICTOR_PATH)
embedder = FaceNet()
print("[SYSTEM] Models loaded successfully.")



# Utilities
def bring_window_to_front(winname):
    cv2.namedWindow(winname, cv2.WINDOW_NORMAL)
    cv2.setWindowProperty(winname, cv2.WND_PROP_TOPMOST, 1)


def configure_camera(cap, calibration_time=2.0):
    """Stabilize the camera for a few seconds before actual capture."""
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



# Database backend

def send_to_php(name, embedding):
    """Send user embedding and info to PHP backend for storage."""
    try:
        data = {
            "name": name,
            "embedding": pickle.dumps(embedding).hex()  # serialize embedding
        }
        # example  endpoint for PHP (implement in PHP later kung tapos ka na)
        response = requests.post(f"{PHP_API_URL}/save_user.php", data=data)
        return response.json()
    except Exception as e:
        print(f"[PHP ERROR] Could not connect to PHP API: {e}")
        return {"status": "error", "message": str(e)}


def fetch_from_php():
    """Fetch all user embeddings from PHP backend."""
    try:
        response = requests.get(f"{PHP_API_URL}/fetch_users.php")
        if response.status_code == 200:
            # Expect PHP to return JSON {name: base64_embedding}
            users_data = response.json()
            users = []
            for name, emb_hex in users_data.items():
                emb = pickle.loads(bytes.fromhex(emb_hex))
                users.append((name, emb))
            return users
        else:
            print("[PHP ERROR] Failed to fetch users.")
            return []
    except Exception as e:
        print(f"[PHP ERROR] {e}")
        return []



# FACIAL RECOGNITION CORE
def _capture_samples(samples_required=SAMPLES_REQUIRED, window_name="Face Capture"):
    cap = cv2.VideoCapture(0)
    if not cap.isOpened():
        return {"status": "error", "message": "Unable to open camera"}

    configure_camera(cap)
    samples = []
    count = 0
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
                x, y, w, h = box
                cv2.rectangle(frame, (x, y), (x + w, y + h), (0, 255, 0), 2)
                cv2.putText(frame, f"Captured {count}/{samples_required}", (x, y - 10),
                            cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)
                bring_window_to_front(window_name)
                cv2.imshow(window_name, frame)
                cv2.waitKey(1)
                time.sleep(0.8)
        else:
            cv2.imshow(window_name, frame)
            bring_window_to_front(window_name)
            cv2.waitKey(1)
    cap.release()
    cv2.destroyAllWindows()
    return {"status": "ok", "samples": samples}


def register_user(name):
    print(f"[REGISTER] Starting registration for {name}")
    result = _capture_samples()
    samples = result.get("samples", [])
    if len(samples) >= SAMPLES_REQUIRED:
        avg_emb = np.mean(np.array(samples), axis=0)
        # Send to PHP backend
        response = send_to_php(name, avg_emb)
        return {"status": "success", "message": f"User {name} registered", "php_response": response}
    return {"status": "error", "message": "Failed to capture sufficient samples"}


def recognize_face(embedding, threshold=0.8):
    users = fetch_from_php()  # Fetch from PHP database 
    min_dist, identity = float("inf"), "Unknown"
    for name, db_emb in users:
        dist = np.linalg.norm(embedding - db_emb)
        if dist < min_dist:
            min_dist, identity = dist, name
    return (identity, min_dist) if min_dist < threshold else ("Unknown", min_dist)


def login_user(name):
    cap = cv2.VideoCapture(0)
    if not cap.isOpened():
        return {"status": "error", "message": "Unable to open camera"}

    configure_camera(cap)
    count = 0
    while count < SAMPLES_REQUIRED:
        ret, frame = cap.read()
        if not ret:
            break
        box = detect_face(frame)
        if box is not None:
            emb = get_embedding(frame, box)
            if emb is not None:
                identity, dist = recognize_face(emb)
                if identity == name:
                    count += 1
                    x, y, w, h = box
                    cv2.rectangle(frame, (x, y), (x + w, y + h), (0, 255, 0), 2)
                    cv2.putText(frame, f"Match {count}/{SAMPLES_REQUIRED}", (x, y - 10),
                                cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)
                    bring_window_to_front("Login")
                    cv2.imshow("Login", frame)
                    cv2.waitKey(1)
                    time.sleep(1)
        else:
            cv2.imshow("Login", frame)
            bring_window_to_front("Login")
            cv2.waitKey(1)
    cap.release()
    cv2.destroyAllWindows()
    return {"status": "success" if count >= SAMPLES_REQUIRED else "error", "matches": count}


def reenroll_user(name):
    """Overwrite old data by re-registering the same user."""
    print(f"[REENROLL] Re-enrolling user: {name}")
    return register_user(name)



# FLASK ROUTES (gateway sang Vite Frontend)
@app.route("/register", methods=["POST"])
def register_route():
    name = request.form.get("name")
    result = register_user(name)
    return jsonify(result)


@app.route("/login", methods=["POST"])
def login_route():
    name = request.form.get("name")
    result = login_user(name)
    return jsonify(result)


@app.route("/reenroll", methods=["POST"])
def reenroll_route():
    name = request.form.get("name")
    result = reenroll_user(name)
    return jsonify(result)


@app.route("/test", methods=["GET"])
def test_connection():
    return jsonify({"status": "ok", "message": "Python Facial Recognition API is running"})

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5001, debug=True)
