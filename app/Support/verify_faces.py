"""Local face detection and matching with OpenCV YuNet and SFace."""

from __future__ import annotations

import os
import sys

site_packages = os.environ.get("LOCAL_FACE_SITE_PACKAGES")
if site_packages and site_packages not in sys.path:
    sys.path.append(site_packages)

import argparse
import json
from pathlib import Path

import cv2
import numpy as np


def respond(payload: dict, exit_code: int = 0) -> None:
    print(json.dumps(payload, separators=(",", ":")))
    raise SystemExit(exit_code)


def load_image(path: str) -> np.ndarray:
    image_path = Path(path)
    if not image_path.is_file():
        respond({"success": False, "code": "image_missing", "message": "The verification image is missing."})

    image = cv2.imread(str(image_path))
    if image is None or image.size == 0:
        respond({"success": False, "code": "invalid_image", "message": "The verification image could not be decoded."})

    return image


def rotations(image: np.ndarray):
    yield 0, image
    yield 90, cv2.rotate(image, cv2.ROTATE_90_CLOCKWISE)
    yield 180, cv2.rotate(image, cv2.ROTATE_180)
    yield 270, cv2.rotate(image, cv2.ROTATE_90_COUNTERCLOCKWISE)


def detect_faces(detector, image: np.ndarray, allow_rotation: bool) -> list[dict]:
    candidates: list[dict] = []
    images = rotations(image) if allow_rotation else [(0, image)]

    for rotation, rotated in images:
        height, width = rotated.shape[:2]
        detector.setInputSize((width, height))
        _, faces = detector.detect(rotated)
        if faces is None:
            continue

        for face in faces:
            x, y, face_width, face_height = face[:4]
            score = float(face[-1])
            if face_width < 40 or face_height < 40:
                continue

            area = float(face_width * face_height)
            candidates.append(
                {
                    "rotation": rotation,
                    "image": rotated,
                    "face": face,
                    "score": score,
                    "area": area,
                    "area_ratio": area / float(width * height),
                }
            )

    return sorted(candidates, key=lambda item: item["area"] * item["score"], reverse=True)


def face_quality(recognizer, candidate: dict) -> dict:
    aligned = recognizer.alignCrop(candidate["image"], candidate["face"].reshape(1, -1))
    gray = cv2.cvtColor(aligned, cv2.COLOR_BGR2GRAY)

    return {
        "aligned": aligned,
        "brightness": float(np.mean(gray)),
        "sharpness": float(cv2.Laplacian(gray, cv2.CV_64F).var()),
    }


def make_models(detector_path: str, recognizer_path: str):
    for model_path in (detector_path, recognizer_path):
        if not Path(model_path).is_file():
            respond(
                {
                    "success": False,
                    "code": "model_missing",
                    "message": "A required local face-verification model is not installed.",
                }
            )

    try:
        detector = cv2.FaceDetectorYN.create(detector_path, "", (320, 320), 0.75, 0.3, 5000)
        recognizer = cv2.FaceRecognizerSF.create(recognizer_path, "")
    except cv2.error:
        respond(
            {
                "success": False,
                "code": "model_load_failed",
                "message": "The local face-verification models could not be loaded.",
            }
        )

    return detector, recognizer


def inspect_document(args) -> None:
    detector, recognizer = make_models(args.detector, args.recognizer)
    image = load_image(args.image)
    candidates = detect_faces(detector, image, allow_rotation=True)

    if not candidates:
        respond(
            {
                "success": False,
                "code": "document_face_missing",
                "message": "No clear portrait was detected on the identity document.",
            }
        )

    best = candidates[0]
    quality = face_quality(recognizer, best)
    respond(
        {
            "success": True,
            "face_detected": True,
            "detection_confidence": round(best["score"] * 100, 2),
            "rotation": best["rotation"],
            "face_width": int(best["face"][2]),
            "face_height": int(best["face"][3]),
            "sharpness": round(quality["sharpness"], 2),
        }
    )


def compare_faces(args) -> None:
    detector, recognizer = make_models(args.detector, args.recognizer)
    document = load_image(args.document)
    live = load_image(args.live)

    document_faces = detect_faces(detector, document, allow_rotation=True)
    if not document_faces:
        respond(
            {
                "success": False,
                "code": "document_face_missing",
                "message": "No clear portrait was detected on the identity document.",
            }
        )

    live_faces = detect_faces(detector, live, allow_rotation=False)
    if not live_faces:
        respond(
            {
                "success": False,
                "code": "live_face_missing",
                "message": "No face was detected in the live camera image. Face the camera and try again.",
            }
        )

    largest_live = live_faces[0]
    other_prominent_faces = [
        face
        for face in live_faces[1:]
        if face["area"] >= largest_live["area"] * 0.2 and face["score"] >= 0.8
    ]
    if other_prominent_faces:
        respond(
            {
                "success": False,
                "code": "multiple_live_faces",
                "message": "More than one face was detected. Make sure only the visitor is visible.",
            }
        )

    if largest_live["area_ratio"] < 0.035:
        respond(
            {
                "success": False,
                "code": "live_face_too_small",
                "message": "Move closer to the camera and keep your face inside the guide.",
            }
        )

    document_quality = face_quality(recognizer, document_faces[0])
    live_quality = face_quality(recognizer, largest_live)

    if live_quality["brightness"] < 35 or live_quality["brightness"] > 225:
        respond(
            {
                "success": False,
                "code": "live_face_lighting",
                "message": "Your face is too dark or overexposed. Adjust the lighting and try again.",
            }
        )

    if live_quality["sharpness"] < 25:
        respond(
            {
                "success": False,
                "code": "live_face_blurry",
                "message": "The camera image is too blurry. Hold still and try again.",
            }
        )

    document_feature = recognizer.feature(document_quality["aligned"])
    live_feature = recognizer.feature(live_quality["aligned"])
    cosine = float(recognizer.match(document_feature, live_feature, cv2.FaceRecognizerSF_FR_COSINE))
    matched = cosine >= args.threshold

    respond(
        {
            "success": True,
            "matched": matched,
            "similarity": round(cosine, 6),
            "similarity_percent": round(max(0.0, min(1.0, cosine)) * 100, 2),
            "threshold": args.threshold,
            "document_detection_confidence": round(document_faces[0]["score"] * 100, 2),
            "live_detection_confidence": round(largest_live["score"] * 100, 2),
            "live_sharpness": round(live_quality["sharpness"], 2),
            "live_brightness": round(live_quality["brightness"], 2),
            "message": (
                "The live face matches the identity-document portrait."
                if matched
                else "The live face does not sufficiently match the identity-document portrait."
            ),
        }
    )


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--detector", required=True)
    parser.add_argument("--recognizer", required=True)
    subparsers = parser.add_subparsers(dest="command", required=True)

    inspect_parser = subparsers.add_parser("inspect")
    inspect_parser.add_argument("--image", required=True)

    compare_parser = subparsers.add_parser("compare")
    compare_parser.add_argument("--document", required=True)
    compare_parser.add_argument("--live", required=True)
    compare_parser.add_argument("--threshold", type=float, default=0.340)

    args = parser.parse_args()
    if args.command == "inspect":
        inspect_document(args)
    else:
        compare_faces(args)


if __name__ == "__main__":
    try:
        main()
    except SystemExit:
        raise
    except Exception:
        respond(
            {
                "success": False,
                "code": "face_verification_failed",
                "message": "Local face verification could not be completed.",
            },
            exit_code=2,
        )
