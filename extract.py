import sys

def extract_method(filename, start_pattern):
    with open(filename, 'r', encoding='utf-8') as f:
        lines = f.readlines()
    
    start_line = -1
    for i, line in enumerate(lines):
        if start_pattern in line:
            start_line = i
            break
    
    if start_line == -1:
        return None
    
    content = []
    brace_count = 0
    started = False
    for i in range(start_line, len(lines)):
        line = lines[i]
        content.append(line)
        brace_count += line.count('{')
        brace_count -= line.count('}')
        if '{' in line:
            started = True
        if started and brace_count == 0:
            break
    return ''.join(content)

form_method = extract_method('temp_user_resource.php', 'public static function form')
table_method = extract_method('temp_user_resource.php', 'public static function table')

if form_method:
    with open('form_method.txt', 'w', encoding='utf-8') as f:
        f.write(form_method)
if table_method:
    with open('table_method.txt', 'w', encoding='utf-8') as f:
        f.write(table_method)
