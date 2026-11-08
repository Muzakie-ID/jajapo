#!/usr/bin/env python3
"""Smoke test Jajapo di muzakie-mp: login demo -> checkout -> tagihan gateway asli -> webhook signed -> lunas.
Jalan dari host: python3 docker/smoke_test.py  (target default http://127.0.0.1:8010)
"""
import hashlib
import hmac
import http.cookiejar
import json
import re
import sys
import time
import urllib.parse
import urllib.request
from html.parser import HTMLParser

BASE = sys.argv[1] if len(sys.argv) > 1 else "https://jajapo.triugm.my.id"
BUYER_URL = "/dev/login/6"  # pembeli demo dari seeder (punya nomor HP)
VARIANT_ID, QTY = 1, 1  # Mac n Cheese Original Rp12.000
SLOT_ID = 1

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
UA = {"User-Agent": "Mozilla/5.0 (X11; Linux x86_64) JajapoSmoke/1.0"}


def xsrf():
    for c in jar:
        if c.name == "XSRF-TOKEN":
            return urllib.parse.unquote(c.value or "")
    return ""


def post(path, data, headers=None):
    body = urllib.parse.urlencode(data, doseq=True).encode()
    h = {"X-XSRF-TOKEN": xsrf(), "Content-Type": "application/x-www-form-urlencoded"}
    h.update(UA)
    h.update(headers or {})
    req = urllib.request.Request(BASE + path, data=body, headers=h, method="POST")
    return opener.open(req, timeout=30)


def get(path):
    req = urllib.request.Request(BASE + path, headers=UA)
    return opener.open(req, timeout=30).read().decode()


class InertiaParser(HTMLParser):
    """Ambil payload Inertia v3: <script data-page="app" type="application/json">{...}</script>."""

    def __init__(self):
        super().__init__()
        self.payload = None
        self._capture = False
        self._buf: list[str] = []

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "script" and a.get("type") == "application/json" and "data-page" in a:
            self._capture = True
            self._buf = []

    def handle_data(self, data):
        if self._capture:
            self._buf.append(data)

    def handle_endtag(self, tag):
        if tag == "script" and self._capture:
            self._capture = False
            try:
                self.payload = json.loads("".join(self._buf))
            except json.JSONDecodeError:
                pass


def props(html):
    p = InertiaParser()
    p.feed(html)
    return (p.payload or {}).get("props", {})


# 1. login pembeli demo
get("/login")
resp = post(BUYER_URL, {})
assert resp.status in (200, 302), f"login gagal: {resp.status}"

# 1b. batalkan order pending lama (aturan: satu pending per PO)
pesanan = json.loads(json.dumps(props(get("/pesanan"))))
orders = pesanan.get("orders") or []
print(f"orders terbaca: {len(orders)} (props keys: {list(pesanan.keys())})")
for o in orders:
    if o.get("status") == "pending_payment":
        old = o["code"]
        r = post(f"/bayar/{old}/batal", {})
        print(f"batal {old}: http {r.status} -> {r.geturl()}")

# 2. buka halaman PO, ambil slot id valid dari props
pre = json.loads(json.dumps(props(get("/danus-dies-ftp/mac-n-cheese-panggang"))))
slots = pre.get("pickup_slots") or pre.get("preorder", {}).get("pickup_slots") or []
slot_id = slots[0]["id"] if slots else SLOT_ID

# 3. checkout
resp = post(
    "/checkout",
    {
        "preorder_id": "1",
        "items[0][variant_id]": str(VARIANT_ID),
        "items[0][qty]": str(QTY),
        "fulfillment": "pickup",
        "slot_id": str(slot_id),
    },
)
assert resp.status in (200, 302), f"checkout gagal: {resp.status}"
bayar_url = resp.geturl()
assert "/bayar/" in bayar_url, f"tidak diarahkan ke halaman bayar: {bayar_url}"
code = bayar_url.rsplit("/", 1)[-1]
print(f"order: {code}")

# 4. halaman bayar -> qr & amount dari props (bentuk: order.payment / order.amount / order.qr...)
pay = json.loads(json.dumps(props(get(f"/bayar/{code}"))))
order = pay.get("order") or {}
payment = order.get("payment") or pay.get("payment") or {}
amount = (
    payment.get("amount")
    or order.get("total")
    or pay.get("amount")
)
qris = (
    payment.get("qris_payload")
    or order.get("qr_string")
    or pay.get("qris_payload")
)
if not amount or not qris:
    # fallback: telusuri kunci berisi data pembayaran di seluruh props
    def find(obj, keys, depth=0):
        if depth > 6 or not isinstance(obj, dict):
            return None
        for k, v in obj.items():
            if k in keys and v:
                return v
            hit = find(v, keys, depth + 1)
            if hit:
                return hit
        return None

    amount = amount or find(pay, {"amount", "final_amount", "finalAmount"})
    qris = qris or find(pay, {"qris_payload", "qris", "qr_payload", "payload"})
assert amount, f"amount tidak ada di props: {json.dumps(pay)[:600]}"
assert qris, f"qr payload kosong; keys: {json.dumps(pay)[:600]}"
print(f"tagihan gateway asli: Rp{amount} (kode unik = amount - {amount - 500 if amount < 20000 else 12000})")

# 5. webhook signed transaction.paid
# Langsung ke 127.0.0.1 (host) biar tidak kena bot-fight Cloudflare: TLS fingerprint python-urllib
# kadang di-challenge 403. Webhook dari gateway asli (Node/undici) terbukti lolos CF (ujian 401).
WEBHOOK_BASE = "http://127.0.0.1:8010"
SECRET = None
env = open("/root/jajapo/.env").read()
m = re.search(r"^PAYMENT_GATEWAY_WEBHOOK_SECRET=(.+)$", env, re.M)
SECRET = m.group(1).strip()
# referensi transaksi di gateway (ULID) — data.id webhook harus sama dengan ini
import subprocess

ref = subprocess.run(
    [
        "docker", "exec", "qrismuz-db-1", "psql", "-U", "jajapo", "-d", "jajapo",
        "-t", "-A", "-c",
        f"select p.gateway_ref from payments p join orders o on o.id = p.order_id where o.code = '{code}'",
    ],
    capture_output=True, text=True, timeout=30,
).stdout.strip()
assert ref, "gateway_ref tidak ketemu"
ts = str(int(time.time() * 1000))
body = json.dumps(
    {
        "id": f"evt-smoke-{int(time.time())}",
        "type": "transaction.paid",
        "created_at": "now",
        "data": {
            "id": ref,
            "merchant_ref": code,
            "status": "PAID",
            "amount": amount,
            "final_amount": amount,
            "paid_at": "2026-11-09T00:00:00Z",
        },
    }
)
sig = hmac.new(SECRET.encode(), f"{ts}.{body}".encode(), hashlib.sha256).hexdigest()
req = urllib.request.Request(
    WEBHOOK_BASE + "/webhooks/payment",
    data=body.encode(),
    headers={
        "Content-Type": "application/json",
        "User-Agent": "qrismuz-webhook/1.0",
        "X-Event-Id": f"evt-smoke-{int(time.time())}",
        "X-Event-Type": "transaction.paid",
        "X-Timestamp": ts,
        "X-Signature": sig,
    },
    method="POST",
)
res = json.loads(urllib.request.urlopen(req, timeout=30).read())
print(f"webhook: {res}")
assert res.get("result") == "processed", f"webhook tidak processed: {res}"

# 6. status order di sisi app
status = json.loads(json.dumps(props(get(f"/bayar/{code}"))))
print(f"halaman bayar setelah webhook: payment={status.get('payment', {}).get('status', '?')}")

print("SMOKE TEST OK")
