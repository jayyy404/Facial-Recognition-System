import os
import cv2
import dlib
import numpy as np
import pickle
import time
from keras_facenet import FaceNet
from flask import Flask, render_template, request

app = Flask(__name__)

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATASET_DIR = os.path.join(BASE_DIR, "dataset")
os.makedirs(DATASET_DIR, exist_ok=True)

HAAR_PATH = os.path.join(BASE_DIR, "resources", "haar_face.xml")
PREDICTOR_PATH = os.path.join(BASE_DIR, "shape_predictor", "shape_predictor_68_face_landmarks.dat")
SAMPLES_REQUIRED = 7 
LOGIN_SCANS = 1      

# Load models
haar_cascade = cv2.CascadeClassifier(cv2.samples.findFile(HAAR_PATH))
predictor = dlib.shape_predictor(PREDICTOR_PATH)
embedder = FaceNet()
print("Models loaded successfully")


# Utilities

def detect_face(frame):
    gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
    faces = haar_cascade.detectMultiScale(gray, 1.3, 6, minSize=(120, 120))
    return max(faces, key=lambda b: b[2]*b[3]) if len(faces) > 0 else None


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


# Face Recognition

def capture_samples(name):
    """Capture multiple samples (for registration or re-enroll)"""
    cap = cv2.VideoCapture(0)
    samples = []
    count = 0

    while count < SAMPLES_REQUIRED:
        ret, frame = cap.read()
        if not ret:
            break
        box = detect_face(frame)
        if box is not None:
            emb = get_embedding(frame, box)
            if emb is not None:
                samples.append(emb)
                count += 1
                cv2.rectangle(frame, (box[0], box[1]), (box[0]+box[2], box[1]+box[3]), (0,255,0), 2)
                cv2.putText(frame, f"Captured {count}/{SAMPLES_REQUIRED}", (box[0], box[1]-10),
                            cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0,255,0), 2)
                cv2.imshow("Capturing Samples", frame)
                cv2.waitKey(1)
                time.sleep(0.8)
        else:
            cv2.imshow("Capturing Samples", frame)
            cv2.waitKey(1)

    cap.release()
    cv2.destroyAllWindows()

    if len(samples) >= SAMPLES_REQUIRED:
        avg_emb = np.mean(samples, axis=0)
        save_user(name, avg_emb)
        return True
    return False


def single_scan_verify(name):
    """Capture one frame and verify user identity."""
    cap = cv2.VideoCapture(0)
    verified = False
    ret, frame = cap.read()
    if ret:
        box = detect_face(frame)
        if box is not None:
            emb = get_embedding(frame, box)
            if emb is not None:
                identity, dist = recognize_face(emb)
                if identity == name:
                    verified = True
                    cv2.rectangle(frame, (box[0], box[1]), (box[0]+box[2], box[1]+box[3]), (0,255,0), 2)
                    cv2.putText(frame, "Verified", (box[0], box[1]-10),
                                cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0,255,0), 2)
                else:
                    cv2.rectangle(frame, (box[0], box[1]), (box[0]+box[2], box[1]+box[3]), (0,0,255), 2)
                    cv2.putText(frame, "Not Recognized", (box[0], box[1]-10),
                                cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0,0,255), 2)
            cv2.imshow("Login Verification", frame)
            cv2.waitKey(1500)
    cap.release()
    cv2.destroyAllWindows()
    return verified


#Flask Routes

@app.route('/')
def index():
    return render_template('index.html')


@app.route('/register', methods=['GET', 'POST'])
def register():
    if request.method == 'POST':
        name = request.form.get("name")
        if not name:
            return render_template('register.html', error="Name is required")

        success = capture_samples(name)
        if success:
            return render_template('success.html', message=f"User '{name}' registered successfully!")
        else:
            return render_template('register.html', error="Failed to capture sufficient samples.")
    return render_template('register.html')


@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        name = request.form.get("name")
        if not name:
            return render_template('login.html', error="Name is required")

        success = single_scan_verify(name)
        if success:
            return render_template('success.html', message=f"User '{name}' verified successfully!")
        else:
            return render_template('login.html', error="Login failed. Face not recognized.")
    return render_template('login.html')


@app.route('/reenroll', methods=['GET', 'POST'])
def reenroll():
    if request.method == 'POST':
        name = request.form.get("name")
        if not name:
            return render_template('reenroll.html', error="Name is required")

        user_dir = os.path.join(DATASET_DIR, name)
        if not os.path.exists(user_dir):
            return render_template('reenroll.html', error=f"User '{name}' not found in dataset.")

        # Remove old embedding files
        for file in os.listdir(user_dir):
            if file.endswith("_embedding.pkl"):
                try:
                    os.remove(os.path.join(user_dir, file))
                except Exception as e:
                    print(f"Warning: {e}")

        success = capture_samples(name)
        if success:
            return render_template('success.html', message=f"User '{name}' successfully re-enrolled!")
        else:
            return render_template('reenroll.html', error="Failed to capture sufficient samples.")
    return render_template('reenroll.html')


if __name__ == "__main__":
    app.run(debug=True)
