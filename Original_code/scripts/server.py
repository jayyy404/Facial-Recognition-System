import base64
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


HAAR_PATH = os.path.join(os.path.dirname(BASE_DIR), "resources", "haar_face.xml")
PREDICTOR_PATH = os.path.join(os.path.dirname(BASE_DIR), "shape_predictor", "shape_predictor_68_face_landmarks.dat")
SAMPLES_REQUIRED = 7

# PHP backend URL 
PHP_API_URL = "http://localhost/Original_code/api"

os.makedirs(DATASET_DIR, exist_ok=True)


# MODEL LOADING(for checking kay gaguba kis a mag load sakon)
if not os.path.exists(HAAR_PATH):
    raise FileNotFoundError(f"Haar cascade not found at {HAAR_PATH}")

haar_cascade = cv2.CascadeClassifier(HAAR_PATH)
if haar_cascade.empty():
    raise RuntimeError(f"Failed to load Haar cascade from {HAAR_PATH}")

predictor = dlib.shape_predictor(PREDICTOR_PATH)  # type: ignore[attr-defined]
    
def scan_for_recognition(max_attempts=120, min_confirmations=2, threshold=0.85):
    """Continuously scan for a recognizable face and return the best match."""
    window_name = "Attendance Recognition"
    cap = cv2.VideoCapture(0)
    if not cap.isOpened():
        return {"status": "error", "message": "Unable to open camera"}

    configure_camera(cap)
    matches = {}
    frames_processed = 0

    try:
        while frames_processed < max_attempts:
            ret, frame = cap.read()
            if not ret:
                break

            frames_processed += 1
            display_frame = frame.copy()
            box = detect_face(frame)

            if box is not None:
                x, y, w, h = box
                cv2.rectangle(display_frame, (x, y), (x + w, y + h), (0, 255, 0), 2)

                embedding = get_embedding(frame, box)
                if embedding is not None:
                    identity, dist = recognize_face(embedding, threshold=threshold)

                    if identity != "Unknown":
                        match = matches.setdefault(identity, {"count": 0, "best_dist": float("inf")})
                        match["count"] += 1
                        if dist < match["best_dist"]:
                            match["best_dist"] = dist

                        cv2.putText(display_frame, f"Checking: {identity}", (x, y - 10),
                                    cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)

                        if match["count"] >= min_confirmations:
                            confidence = max(0.0, 1.0 - match["best_dist"])
                            bring_window_to_front(window_name)
                            cv2.imshow(window_name, display_frame)
                            cv2.waitKey(500)
                            return {
                                "status": "success",
                                "identity": identity,
                                "distance": float(match["best_dist"]),
                                "confidence": float(confidence),
                                "frames": frames_processed
                            }
                    else:
                        cv2.putText(display_frame, "Analyzing face...", (x, y - 10),
                                    cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2)
            else:
                cv2.putText(display_frame, "No face detected", (20, 40),
                            cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 255), 2)

            bring_window_to_front(window_name)
            cv2.imshow(window_name, display_frame)
            if cv2.waitKey(1) & 0xFF == 27:  
                return {"status": "error", "message": "Recognition cancelled by user"}

        if matches:
        
            identity, info = min(matches.items(), key=lambda item: item[1]["best_dist"])
            return {
                "status": "partial",
                "identity": identity,
                "distance": float(info["best_dist"]),
                "confidence": float(max(0.0, 1.0 - info["best_dist"])),
                "frames": frames_processed,
                "message": "Face detected but confirmation threshold not met"
            }

        return {
            "status": "unrecognized",
            "message": "Unable to recognize any registered user",
            "frames": frames_processed
        }
    finally:
        cap.release()
        cv2.destroyWindow(window_name)

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
    faces = haar_cascade.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=3, minSize=(60, 60))
    return max(faces, key=lambda b: b[2] * b[3]) if len(faces) > 0 else None


def get_embedding(frame, box):
    x, y, w, h = box
    face_crop = frame[y:y+h, x:x+w]
    if face_crop.size == 0:
        return None
    face_crop = cv2.resize(face_crop, (160, 160))
    return embedder.embeddings([face_crop])[0]

# Database backend
def send_to_php(name, embedding, user_id=None, role="Student", dept=None, username=None, password=None):
    """Send user embedding and info to PHP backend for storage."""
    try:
        data = {
            "name": name,
            "embedding": pickle.dumps(embedding).hex(),  # serialize embedding
            "id": user_id,
            "role": role,
            "dept": dept,
            "username": username or name.lower().replace(" ", ""),
            "password": password or "password123"  
        }
        # Drop any keys with None values to avoid sending "None" strings
        data = {k: v for k, v in data.items() if v is not None}
        # Send to API endpoint
        response = requests.post(f"{PHP_API_URL}/save_user.php", data=data)
        print(f"[PHP] Sent data to {PHP_API_URL}/save_user.php")
        return response.json()
    except Exception as e:
        print(f"[PHP ERROR] Could not connect to PHP API: {e}")
        return {"status": "error", "message": str(e)}


def fetch_from_php():
    """Fetch all user embeddings from PHP backend."""
    try:
        response = requests.get(f"{PHP_API_URL}/fetch_users.php?embeddings=true")
        if response.status_code == 200:
            data = response.json()
            users = []
            if "users" in data and len(data["users"]) > 0:
                for user in data["users"]:
                    if "embedding" in user and user["embedding"]:
                        try:
                            # Convert embedding from hex to bytes and deserialize
                            emb = pickle.loads(bytes.fromhex(user["embedding"]))
                            users.append((user["name"], emb))
                        except Exception as e:
                            print(f"[EMBEDDING ERROR] Failed to parse embedding for {user['name']}: {e}")
            return users
        else:
            print(f"[PHP ERROR] Failed to fetch users. Status: {response.status_code}")
            return []
    except Exception as e:
        print(f"[PHP ERROR] {e}")
        return []


# FACIAL RECOGNITION CORE
def decode_image_from_data_url(data_url: str):
    if not data_url:
        return None
    try:
        if data_url.startswith('data:'):
            _, encoded = data_url.split(',', 1)
        else:
            encoded = data_url
        img_bytes = base64.b64decode(encoded)
    except (ValueError, TypeError):
        return None

    np_buffer = np.frombuffer(img_bytes, dtype=np.uint8)
    if np_buffer.size == 0:
        return None
    return cv2.imdecode(np_buffer, cv2.IMREAD_COLOR)


def embeddings_from_frames(frames):
    embeddings = []
    for frame_data in frames or []:
        frame = decode_image_from_data_url(frame_data)
        if frame is None:
            continue
        box = detect_face(frame)
        if box is None:
            continue
        emb = get_embedding(frame, box)
        if emb is not None:
            embeddings.append(emb)
        if len(embeddings) >= SAMPLES_REQUIRED:
            break
    return embeddings


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


def register_user(name, user_id=None, role="Student", dept=None, username=None, password=None, frames=None):
    print(f"[REGISTER] Starting registration for {name}")

    frame_list = frames or []
    if not frame_list:
        return {"status": "error", "message": "No image frames supplied for registration."}

    samples = embeddings_from_frames(frame_list)
    print(f"[REGISTER] Received {len(samples)}/{SAMPLES_REQUIRED} usable samples from browser")

    if len(samples) < SAMPLES_REQUIRED:
        return {
            "status": "error",
            "message": f"Insufficient usable samples ({len(samples)}) from uploaded frames."
        }

    avg_emb = np.mean(np.array(samples), axis=0)

    print("[REGISTER] Sending data to PHP API")
    response = send_to_php(
        name,
        avg_emb,
        user_id=user_id,
        role=role,
        dept=dept,
        username=username,
        password=password
    )
    print(f"[REGISTER] PHP API response: {response}")

    if not isinstance(response, dict) or response.get("status") != "success":
        message = "PHP service reported an error while saving the embedding"
        if isinstance(response, dict):
            message = response.get("message", message)
        return {
            "status": "error",
            "message": message,
            "php_response": response
        }

    return {
        "status": "success",
        "message": f"User {name} registered successfully",
        "php_response": response,
        "frames" : len(samples)
    }


def recognize_face(embedding, threshold=0.8):
    users = fetch_from_php()  # Fetch from PHP database 
    min_dist, identity = float("inf"), "Unknown"
    for name, db_emb in users:
        dist = np.linalg.norm(embedding - db_emb)
        if dist < min_dist:
            min_dist, identity = dist, name
    
    is_recognized = min_dist < threshold
    result = (identity, min_dist) if is_recognized else ("Unknown", min_dist)
    
    return result


def recognize_from_frame_data(frame_data, threshold=0.8):
    frame = decode_image_from_data_url(frame_data)
    if frame is None:
        return {"status": "error", "message": "Invalid or empty frame data provided."}

    box = detect_face(frame)
    if box is None:
        return {
            "status": "unrecognized",
            "message": "No face detected in the provided frame."
        }

    embedding = get_embedding(frame, box)
    if embedding is None:
        return {"status": "error", "message": "Failed to extract facial features from frame."}

    identity, dist = recognize_face(embedding, threshold=threshold)
    confidence = max(0.0, 1.0 - float(dist))

    if identity != "Unknown":
        return {
            "status": "success",
            "recognized": True,
            "name": identity,
            "confidence": confidence,
            "distance": float(dist),
            "frames": 1
        }

    return {
        "status": "unrecognized",
        "recognized": False,
        "message": "Face not recognized",
        "confidence": confidence,
        "distance": float(dist),
        "frames": 1
    }


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


def reenroll_user(name, user_id=None, role="Student", dept=None, username=None, password=None, frames=None):
    """Overwrite old data by re-registering the same user."""
    print(f"[REENROLL] Re-enrolling user: {name}")
    return register_user(
        name,
        user_id=user_id,
        role=role,
        dept=dept,
        username=username,
        password=password,
        frames=frames
    )


# FLASK ROUTES 
@app.route("/register", methods=["POST"])
def register_route():
    try:
        payload = request.form if request.form else (request.get_json(silent=True) or {})

        name = payload.get("name")
        user_id = payload.get("id") or payload.get("user_id")
        role = payload.get("role", "Student")
        dept = payload.get("dept")
        username = payload.get("username")
        password = payload.get("password")
        
        # Log the request data for debugging (kwaa na ni kay ga work na hahahah)
        print(f"[REGISTER] Received registration request:")
        print(f"  - name: {name}")
        print(f"  - user_id: {user_id}")
        print(f"  - role: {role}")
        print(f"  - dept: {dept}")
        print(f"  - username: {username}")
        
        if not name:
            print("[REGISTER] Error: Name is required")
            return jsonify({"status": "error", "message": "Name is required"}), 400
            
        print(f"[REGISTER] Starting facial recognition for {name}")
        
        # Capture face and register user
        result = register_user(
            name,
            user_id=user_id,
            role=role,
            dept=dept,
            username=username,
            password=password
        )
        
        print(f"[REGISTER] Registration result: {result}")
        
        # Check if registration was successful and embedding was generated
        if result["status"] == "success" and "php_response" in result:
            print(f"[REGISTER] Successfully registered {name}")
            return jsonify(result)
            
        return jsonify(result)
    except Exception as e:
        print(f"[ERROR] Registration error: {e}")
        return jsonify({"status": "error", "message": f"Registration error: {str(e)}"}), 500


@app.route("/login", methods=["POST"])
def login_route():
    try:
        payload = request.form if request.form else (request.get_json(silent=True) or {})
        name = payload.get("name")
        
        if not name:
            return jsonify({"status": "error", "message": "Name is required"}), 400
            
        print(f"[LOGIN] Received login request for {name}")
        result = login_user(name)
        return jsonify(result)
    except Exception as e:
        print(f"[ERROR] Login error: {e}")
        return jsonify({"status": "error", "message": str(e)}), 500


@app.route("/reenroll", methods=["POST"])
def reenroll_route():
    try:
        payload = request.form if request.form else (request.get_json(silent=True) or {})

        name = payload.get("name")
        user_id = payload.get("id") or payload.get("user_id")
        role = payload.get("role", "Student")
        dept = payload.get("dept")
        username = payload.get("username")
        password = payload.get("password")
        
        if not name:
            return jsonify({"status": "error", "message": "Name is required"}), 400
            
        print(f"[REENROLL] Received reenrollment request for {name}")
        result = reenroll_user(
            name,
            user_id=user_id,
            role=role,
            dept=dept,
            username=username,
            password=password
        )
        return jsonify(result)
    except Exception as e:
        print(f"[ERROR] Reenrollment error: {e}")
        return jsonify({"status": "error", "message": str(e)}), 500


@app.route("/recognize", methods=["POST"])
def recognize_route():
    try:
        scan_result = scan_for_recognition()

        status = scan_result.get("status")
        if status == "success":
            return jsonify({
                "status": "success",
                "identity": scan_result.get("identity"),
                "confidence": float(scan_result.get("confidence", 0.0)),
                "distance": float(scan_result.get("distance", 0.0)),
                "frames": scan_result.get("frames")
            })
        elif status == "partial":
            return jsonify(scan_result), 206
        elif status == "unrecognized":
            return jsonify(scan_result), 404
        else:
            return jsonify(scan_result), 500
    except Exception as e:
        print(f"[ERROR] Recognition error: {e}")
        return jsonify({"status": "error", "message": str(e)}), 500


@app.route("/test", methods=["GET"])
def test_connection():
    return jsonify({
        "status": "ok", 
        "message": "Python Facial Recognition API is running",
        "timestamp": time.strftime("%Y-%m-%d %H:%M:%S"),
        "models_loaded": {
            "haar_cascade": haar_cascade is not None,
            "predictor": predictor is not None,
            "embedder": embedder is not None
        }
    })

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5001, debug=True)
