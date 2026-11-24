import re, glob, os, sys
root='.'
mig=sorted(glob.glob('database/migrations/*.php'))
tables={}   # name -> {'cols':{col:len}, 'pk':(col,len), 'file':f}
fks=[]      # (file, table, col, parent)
errors=[]
order={}
for i,f in enumerate(mig):
    s=open(f).read()
    for m in re.finditer(r"Schema::create\('(\w+)'",s):
        t=m.group(1)
        if t in tables: errors.append(f"DUPLIKAT create tabel {t}: {tables[t]['file']} & {f}")
        tables[t]={'cols':{}, 'pk':None, 'file':f}; order[t]=i
    # split blocks per Schema::create / Schema::table
    blocks=re.split(r"(?=Schema::(?:create|table)\()",s)
    for b in blocks:
        mm=re.match(r"Schema::(create|table)\('(\w+)'",b)
        if not mm: continue
        t=mm.group(2)
        for c in re.finditer(r"\$table->string\('(\w+)'(?:,\s*(\d+))?\)([^;]*);",b):
            col,ln,rest=c.group(1),int(c.group(2) or 255),c.group(3)
            if mm.group(1)=='create':
                tables[t]['cols'][col]=ln
                if 'primary()' in rest and tables[t]['pk'] is None: tables[t]['pk']=(col,ln)
        for c in re.finditer(r"\$table->foreign\('(\w+)'\)->references\('(\w+)'\)->on\('(\w+)'\)",b):
            fks.append((f,t,c.group(1),c.group(3),c.group(2)))
print("Tabel:",len(tables))
expected={'users':('USR001',6),'branches':('BRN01',5),'categories':('CTG01',5),'products':('PRD0001',7),
 'branch_stocks':('BST0001',7),'transactions':('TRX000001',9),'transaction_details':('TDT000001',9),
 'transaction_payments':('PAY000001',9),'stock_movements':('SMV000001',9),'roles':('ROL01',5),'permissions':('PRM001',6),
 'stock_distributions':('DST000001',9),'stock_distribution_details':('SDD000001',9)}
for t,(ex,ln) in expected.items():
    pk=tables.get(t,{}).get('pk')
    ok = pk and pk[1]==ln==len(ex)
    print(f"  {t:28s} PK {pk} contoh {ex} ({len(ex)})", "OK" if ok else "!! BEDA")
    if not ok: errors.append(f"PK {t} tidak sesuai standar")
for (f,t,col,parent,pcol) in fks:
    if parent not in tables: errors.append(f"FK {t}.{col} -> tabel {parent} tidak ada"); continue
    cl=tables[t]['cols'].get(col)
    if t=='users' and col=='branch_id': cl=tables['users']['cols']['branch_id']
    pl=tables[parent]['pk'][1]
    if cl!=pl: errors.append(f"FK {t}.{col}({cl}) != PK {parent}.{pcol}({pl})")
    if order.get(parent,-1)>mig.index(f): errors.append(f"urutan: FK {t}.{col} dideklarasikan di {os.path.basename(f)} sebelum tabel {parent} ada")
print("FK diperiksa:",len(fks))
# kolom FK implisit tanpa foreign() (sessions.user_id, polymorphic)
for t,col,par in [('sessions','user_id','users'),('model_has_roles','model_id','users'),('model_has_permissions','model_id','users')]:
    if tables[t]['cols'][col]!=tables[par]['pk'][1]: errors.append(f"{t}.{col} != PK {par}")
if tables['stock_movements']['cols']['reference_id']<max(tables['transactions']['pk'][1],tables['stock_distributions']['pk'][1]): errors.append('reference_id terlalu pendek')
# model prefix/pad
tmap={'User':'users','Branch':'branches','Category':'categories','Product':'products','BranchStock':'branch_stocks','Transaction':'transactions',
 'TransactionDetail':'transaction_details','TransactionPayment':'transaction_payments','StockMovement':'stock_movements','Role':'roles','Permission':'permissions',
 'StockDistribution':'stock_distributions','StockDistributionDetail':'stock_distribution_details'}
cap={'users':999,'branches':99,'categories':99,'products':9999,'branch_stocks':9999,'transactions':999999,'transaction_details':999999,'transaction_payments':999999,'stock_movements':999999,'roles':99,'permissions':999}
prefixes={}
for cls,t in tmap.items():
    s=open(f'app/Models/{cls}.php').read()
    pre=re.search(r"codePrefix\(\): string\s*\{\s*return '(\w+)'",s).group(1)
    pad=int(re.search(r"codePad\(\): int\s*\{\s*return (\d+)",s).group(1))
    L=len(pre)+pad; pkl=tables[t]['pk'][1]
    st="OK" if L==pkl else "!! BEDA"
    if L!=pkl: errors.append(f"{cls}: prefix+pad={L} != PK {t}={pkl}")
    if t in cap and 10**pad-1!=cap[t]: errors.append(f"{cls}: kapasitas {10**pad-1} != spesifikasi {cap[t]}")
    if pre in prefixes: errors.append(f"prefix ganda {pre}: {cls} & {prefixes[pre]}")
    prefixes[pre]=cls
    print(f"  model {cls:24s} {pre}+{pad} = {L}  PK({t})={pkl} kapasitas {10**pad-1:>7,} {st}")
# sintaks kasar
def strip(s):
    s=re.sub(r"//.*","",s); s=re.sub(r"/\*.*?\*/","",s,flags=re.S)
    s=re.sub(r"'(?:\\.|[^'\\])*'","''",s); s=re.sub(r'"(?:\\.|[^"\\])*"','""',s); return s
n=0
for f in glob.glob('**/*.php',recursive=True):
    s=strip(open(f).read()); n+=1
    if not open(f).read().startswith('<?php'): errors.append(f"{f}: tidak diawali <?php")
    for a,b in ['{}','()','[]']:
        if s.count(a)!=s.count(b): errors.append(f"{f}: kurung {a}{b} tidak seimbang ({s.count(a)} vs {s.count(b)})")
print("File PHP diperiksa:",n)
# sisa 36 / format lama
for f in glob.glob('**/*.php',recursive=True):
    t=open(f).read()
    if re.search(r"string\('\w+', 36\)",t): errors.append(f"{f}: masih VARCHAR(36)")
    if "'CAB'" in t or "'DET'" in t: errors.append(f"{f}: prefix lama")
print("\nERROR:" if errors else "\nSEMUA PEMERIKSAAN LULUS")
for e in errors: print(" -",e)
sys.exit(1 if errors else 0)
