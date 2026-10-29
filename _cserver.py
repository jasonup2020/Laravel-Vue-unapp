# -*- coding: utf-8 -*-
"""
C-end (uniapp H5) static server: serves frontend-uniapp/dist/build/h5
and reverse-proxies /api to the Laravel backend on 127.0.0.1:8000.
C 端 uniapp H5 静态服务：托管 dist/build/h5 产物，/api 反向代理到 8000 后端。
"""
import http.server
import socketserver
import os
import urllib.request

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "frontend-uniapp", "dist", "build", "h5")
BACKEND = "http://127.0.0.1:8000"
PORT = 5174


class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=ROOT, **kwargs)

    def do_GET(self):
        if self.path.startswith("/api/"):
            self._proxy()
            return
        # SPA fallback: unknown path -> index.html
        full = os.path.join(ROOT, self.path.lstrip("/"))
        if not os.path.isfile(full):
            self.path = "/index.html"
        super().do_GET()

    def do_POST(self):
        self._proxy_body()

    def do_PUT(self):
        self._proxy_body()

    def do_DELETE(self):
        self._proxy_body()

    def _proxy_body(self):
        """Read request body and forward it to the backend (GET has none)."""
        length = int(self.headers.get("Content-Length") or 0)
        body = self.rfile.read(length) if length else b""
        url = BACKEND + self.path
        req = urllib.request.Request(url, data=body, method=self.command)
        for k in ("Content-Type", "X-Idempotency-Key", "Authorization", "Accept-Language", "X-Client-Fingerprint"):
            if self.headers.get(k):
                req.add_header(k, self.headers[k])
        try:
            with urllib.request.urlopen(req, timeout=30) as r:
                out = r.read()
                self.send_response(r.status)
                for k, v in r.headers.items():
                    if k.lower() in ("content-type", "cache-control", "etag"):
                        self.send_header(k, v)
                self.send_header("Content-Length", str(len(out)))
                self.end_headers()
                self.wfile.write(out)
        except urllib.error.HTTPError as e:
            self.send_response(e.code)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            out = e.read()
            self.send_header("Content-Length", str(len(out)))
            self.end_headers()
            self.wfile.write(out)
        except Exception as e:
            self.send_response(502)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            out = '{"success":false,"code":5000,"msg":"proxy error"}'.encode("utf-8")
            self.send_header("Content-Length", str(len(out)))
            self.end_headers()
            self.wfile.write(out)

    def _proxy(self):
        url = BACKEND + self.path
        req = urllib.request.Request(url, method="GET")
        auth = self.headers.get("Authorization")
        logf = os.path.join(os.path.dirname(os.path.abspath(__file__)), "_cserver_debug.log")
        with open(logf, "a", encoding="utf-8") as f:
            f.write(f"GET {self.path} auth={auth[:30] if auth else 'NONE'} fp={self.headers.get('X-Client-Fingerprint')}\n")
        for k in ("Authorization", "Accept-Language", "X-Client-Fingerprint", "X-Idempotency-Key", "Accept", "Content-Type"):
            if self.headers.get(k):
                req.add_header(k, self.headers[k])
        try:
            with urllib.request.urlopen(req, timeout=30) as r:
                body = r.read()
                self.send_response(r.status)
                for k, v in r.headers.items():
                    # content-length 由下方统一按实际 body 设置，避免重复头导致 XHR 挂起
                    if k.lower() in ("content-type", "cache-control", "etag"):
                        self.send_header(k, v)
                self.send_header("Content-Length", str(len(body)))
                self.end_headers()
                self.wfile.write(body)
        except urllib.error.HTTPError as e:
            self.send_response(e.code)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            out = e.read()
            self.send_header("Content-Length", str(len(out)))
            self.end_headers()
            self.wfile.write(out)
        except Exception as e:
            self.send_response(502)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            body = '{"success":false,"code":5000,"msg":"proxy error"}'.encode("utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)

    def log_message(self, fmt, *args):
        pass  # keep console quiet / 保持控制台安静


if __name__ == "__main__":
    with socketserver.ThreadingTCPServer(("0.0.0.0", PORT), Handler) as httpd:
        print(f"C-end H5 server on http://127.0.0.1:{PORT} (root={ROOT})")
        httpd.serve_forever()
