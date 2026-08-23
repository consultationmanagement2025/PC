import mysql.connector

try:
    conn = mysql.connector.connect(host='localhost', user='root', password='', database='capstone')
except:
    try:
        conn = mysql.connector.connect(host='localhost', user='root', password='', database='pcms')
    except Exception as e:
        print("DB connect error:", e)
        exit(1)

cursor = conn.cursor(dictionary=True)

print("=== CONSULTATIONS IN DB ===")
cursor.execute("SELECT id, title, status, type, response_mode FROM consultations")
consultations = cursor.fetchall()
for c in consultations:
    print(c)

print("\n=== FEEDBACK COUNT PER CONSULTATION ===")
cursor.execute("SELECT consultation_id, COUNT(*) as cnt FROM feedback GROUP BY consultation_id")
fb_counts = cursor.fetchall()
for f in fb_counts:
    print(f)

print("\n=== POSTS COUNT PER CONSULTATION ===")
cursor.execute("SELECT consultation_id, COUNT(*) as cnt FROM posts GROUP BY consultation_id")
p_counts = cursor.fetchall()
for p in p_counts:
    print(p)

print("\n=== SURVEY VOTES COUNT PER CONSULTATION ===")
cursor.execute("SHOW TABLES LIKE 'survey%'")
stables = cursor.fetchall()
print("Survey tables:", stables)

conn.close()
