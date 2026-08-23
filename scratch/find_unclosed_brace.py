with open(r'c:\xampp\htdocs\CAP101\PC\app-features.js', 'r', encoding='utf-8', errors='ignore') as f:
    lines = f.readlines()

stack = []
for idx, line in enumerate(lines):
    # Remove strings & template literals roughly
    cleaned = line
    for char_idx, char in enumerate(line):
        if char == '{':
            stack.append((idx + 1, line.strip()[:60]))
        elif char == '}':
            if stack:
                stack.pop()

print("Unclosed braces remaining in stack:")
for item in stack:
    print(f"Line {item[0]}: {item[1]}")
