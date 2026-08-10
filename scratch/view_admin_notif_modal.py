import os, re

filepath = r'c:\xampp\htdocs\CAP101\PC\app-features.js'
with open(filepath, 'r', encoding='utf-8') as f:
    code = f.read()

idx = code.find("notif-detail-modal")
if idx != -1:
    print("=== NOTIF DETAIL MODAL ===")
    print(code[idx:idx+1200])

idx2 = code.find("renderNotifications")
if idx2 != -1:
    print("=== RENDER NOTIFICATIONS ===")
    print(code[idx2:idx2+1200])
