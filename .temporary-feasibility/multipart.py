# Raw HTTP probe using synthetic fixtures only; no application or database.
import json, sys, time, urllib.request, urllib.error
from pathlib import Path
root=Path(sys.argv[1])
url="http://127.0.0.1:18742/"
for _ in range(50):
    try:
        with urllib.request.urlopen(url, timeout=1) as response:
            json.load(response)
        break
    except OSError:
        time.sleep(.1)
else:
    raise RuntimeError("local PHP server unavailable")
first=(root/"first.png").read_bytes()
second=(root/"second.png").read_bytes()
exact=(root/"size-2097152.png").read_bytes()
over=(root/"size-2097153.png").read_bytes()
def send(case, parts):
    boundary="f03-synthetic-boundary-741129"
    chunks=[]
    for name,filename,data in parts:
        header=f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"'
        if filename is not None:
            header+=f'; filename="{filename}"\r\nContent-Type: image/png'
        chunks.append(header.encode()+b"\r\n\r\n"+data+b"\r\n")
    body=b"".join(chunks)+f"--{boundary}--\r\n".encode()
    req=urllib.request.Request(url,body,{"Content-Type":f"multipart/form-data; boundary={boundary}"},method="POST")
    with urllib.request.urlopen(req,timeout=20) as response:
        result=json.load(response)
    print("PROBE "+json.dumps({"case":case,**result},separators=(",",":")),flush=True)
    assert result["php"].startswith("8.2."), result["php"]
    return result
def f(name,filename,data): return (name,filename,data)
a=f("avatar","first.png",first); b=f("avatar","second.png",second)
aa=f("avatar[]","first.png",first); bb=f("avatar[]","second.png",second)
one=send("multipart_single",[a]); assert one["files"]["avatar"]["name"]=="first.png"
dup=send("multipart_duplicate_scalar",[a,b])
arr=send("multipart_brackets",[aa,bb])
mixed1=send("multipart_array_then_scalar",[aa,b])
mixed2=send("multipart_scalar_then_array",[a,bb])
extra=send("multipart_unknown_file",[a,f("other","second.png",second)])
override=send("multipart_override",[("_method",None,b"PUT"),a])
at=send("multipart_exact_2MiB",[f("avatar","exact.png",exact)])
plus=send("multipart_2MiB_plus_one",[f("avatar","over.png",over)])
empty=send("multipart_empty",[f("avatar","empty.png",b"")])
big=send("multipart_post_over_4MiB",[f("avatar","exact.png",exact),f("other","exact.png",exact)])
print("PROBE "+json.dumps({"case":"multipart_summary","duplicate_observable":isinstance(dup["files"].get("avatar",{}).get("name"),list),"strict_duplicate_rejection_proven":False,"exact_error":at["files"]["avatar"]["error"],"over_error":plus["files"]["avatar"]["error"],"post_over_empty":not big["files"] and not big["post_keys"],"laravel_override_tested":False}),flush=True)
assert at["files"]["avatar"]["error"]==0 and at["files"]["avatar"]["size"]==2097152
assert plus["files"]["avatar"]["error"]==1
assert not big["files"] and not big["post_keys"]
