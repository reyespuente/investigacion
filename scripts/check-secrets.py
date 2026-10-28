"""Check the staged/public source tree; never print a discovered secret."""
import pathlib,re,subprocess,sys
root=pathlib.Path(__file__).resolve().parents[1]
files=subprocess.check_output(['git','ls-files','--cached','--others','--exclude-standard','-z'],cwd=root).decode().split('\0')
errors=[]
for name in filter(None,files):
 p=root/name
 if not p.is_file():continue
 if name.startswith(('private/','imports/','public/brand/')) and not name.endswith('.gitkeep'):errors.append(name);continue
 if p.name.startswith('.env') and p.name!='.env.example':errors.append(name);continue
 if p.suffix in ('.sql','.csv','.xlsx') or name.endswith(('.jsonl','.jsonl.gz')):errors.append(name);continue
 if p.stat().st_size>3_000_000:continue
 try:text=p.read_text(encoding='utf-8')
 except UnicodeDecodeError:continue
 patterns=[r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----',r'(?m)^(?:AWS_SECRET_ACCESS_KEY|GOOGLE_CLIENT_SECRET|TURNSTILE_SECRET_KEY|DB_PASSWORD|ADMIN_PASSWORD)=([^\s#]+)',r'\b[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}\b']
 if any(re.search(pattern,text) for pattern in patterns):errors.append(name)
if errors:print('Revisar archivos antes de publicar (sin mostrar valores):\n'+'\n'.join(sorted(set(errors))));sys.exit(1)
print('No se detectaron secretos ni archivos de importación privados en el árbol publicable.')
