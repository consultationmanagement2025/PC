import subprocess

res = subprocess.run(["node", "-c", "app-features.js"], capture_output=True, text=True)
print(res.stdout)
print(res.stderr)
