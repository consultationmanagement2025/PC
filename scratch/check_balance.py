with open(r'c:\xampp\htdocs\CAP101\PC\app-features.js', 'r', encoding='utf-8', errors='ignore') as f:
    content = f.read()

brace_count = 0
paren_count = 0
bracket_count = 0

lines = content.split('\n')
for idx, line in enumerate(lines):
    for char in line:
        if char == '{': brace_count += 1
        elif char == '}': brace_count -= 1
        elif char == '(': paren_count += 1
        elif char == ')': paren_count -= 1
        elif char == '[': bracket_count += 1
        elif char == ']': bracket_count -= 1
    
    if brace_count < 0:
        print(f"Extra closing brace at line {idx+1}")
        break

print(f"Final brace balance: {brace_count}")
print(f"Final paren balance: {paren_count}")
print(f"Final bracket balance: {bracket_count}")
