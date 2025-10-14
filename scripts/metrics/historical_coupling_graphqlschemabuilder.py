#!/usr/bin/env python3
import os
import re
import json
import subprocess
from datetime import datetime, timedelta
from collections import defaultdict, namedtuple

REPO_ROOT = os.getcwd()
OUT_DIR = os.path.join(REPO_ROOT, "scripts", "metrics", "out")
os.makedirs(OUT_DIR, exist_ok=True)

PhpClass = namedtuple("PhpClass", ["name", "short", "namespace", "path", "is_abstract", "is_interface", "uses"])

NAMESPACE_RE = re.compile(r"^\s*namespace\s+([^;]+);", re.MULTILINE)
CLASS_RE = re.compile(r"^\s*(abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)", re.MULTILINE)
INTERFACE_RE = re.compile(r"^\s*interface\s+([A-Za-z_][A-Za-z0-9_]*)", re.MULTILINE)
USE_RE = re.compile(r"^\s*use\s+([^;]+);", re.MULTILINE)

def run(cmd):
    res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, cwd=REPO_ROOT)
    if res.returncode != 0:
        raise RuntimeError(f"Command failed: {' '.join(cmd)}\n{res.stderr}")
    return res.stdout

def month_range(start: datetime, end: datetime):
    cur = datetime(start.year, start.month, 1)
    end_month = datetime(end.year, end.month, 1)
    while cur <= end_month:
        yield cur
        y = cur.year + (1 if cur.month == 12 else 0)
        m = 1 if cur.month == 12 else cur.month + 1
        cur = datetime(y, m, 1)

def file_first_last_dates(path):
    out = run(["git", "log", "--format=%ad", "--date=iso-strict", "--reverse", "--", path]).strip().splitlines()
    if not out:
        raise SystemExit(f"No history for file: {path}")
    return datetime.fromisoformat(out[0]), datetime.fromisoformat(out[-1])

def commit_before(date_iso: str):
    out = run(["git", "rev-list", "-1", f"--before={date_iso}", "HEAD"]).strip()
    return out or None

def commit_before_with_file(date_iso: str, path: str):
    out = run(["git", "rev-list", "-1", f"--before={date_iso}", "HEAD", "--", path]).strip()
    return out or None

def file_exists_in_commit(commit: str, path: str):
    try:
        run(["git", "cat-file", "-e", f"{commit}:{path}"])
        return True
    except Exception:
        return False

def list_php_files(commit: str):
    out = run(["git", "ls-tree", "-r", "--name-only", commit])
    return [l for l in out.splitlines() if l.startswith("src/") and l.endswith(".php")]

def show_file(commit: str, path: str):
    return run(["git", "show", f"{commit}:{path}"])

def parse_php_text(text: str, path_hint: str):
    ns_match = NAMESPACE_RE.search(text)
    namespace = ns_match.group(1).strip() if ns_match else ""
    classes = []
    for m in INTERFACE_RE.finditer(text):
        cls = m.group(1)
        fqn = (namespace + "\\" + cls) if namespace else cls
        classes.append(PhpClass(fqn, cls, namespace, path_hint, False, True, set()))
    for m in CLASS_RE.finditer(text):
        is_abs = bool(m.group(1))
        cls = m.group(2)
        fqn = (namespace + "\\" + cls) if namespace else cls
        classes.append(PhpClass(fqn, cls, namespace, path_hint, is_abs, False, set()))
    uses = set()
    for m in USE_RE.finditer(text):
        raw = m.group(1)
        parts = []
        if '{' in raw and '}' in raw:
            prefix = raw.split('{', 1)[0].rstrip('\\').strip()
            group = raw.split('{', 1)[1].rsplit('}', 1)[0]
            for item in group.split(','):
                item = item.strip()
                base = item.split(' as ', 1)[0].strip()
                parts.append(prefix + "\\" + base)
        else:
            for item in raw.split(','):
                base = item.strip()
                base = base.split(' as ', 1)[0].strip()
                parts.append(base)
        for p in parts:
            if p:
                uses.add(p)
    if classes:
        classes = [c._replace(uses=set(uses)) for c in classes]
    return classes

def pick_fqn_for_file(classes, filename_base: str, override_fqn: str | None):
    if override_fqn:
        for c in classes:
            if c.name == override_fqn:
                return override_fqn
        return override_fqn
    if not classes:
        return None
    for c in classes:
        if c.short.lower() == filename_base.lower():
            return c.name
    return classes[0].name

def build_graph(classes):
    name_to_fqn = {}
    for c in classes:
        name_to_fqn[c.name] = c.name
        name_to_fqn[c.short] = c.name
    deps = defaultdict(set)
    for c in classes:
        for u in c.uses:
            target = name_to_fqn.get(u) or name_to_fqn.get(u.split('\\')[-1])
            if target and target != c.name:
                deps[c.name].add(target)
    for c in classes:
        deps.setdefault(c.name, set())
    rev = defaultdict(set)
    for s, outs in deps.items():
        for d in outs:
            rev[d].add(s)
    for c in classes:
        rev.setdefault(c.name, set())
    return deps, rev

def svg_two_series(points, title, label_a, label_b, out_path):
    # points: list of (date_str, a_val, b_val)
    width = 900
    height = 320
    left_pad = 60
    right_pad = 20
    top_pad = 40
    bottom_pad = 60
    n = max(1, len(points))
    x_step = (width - left_pad - right_pad) / max(1, n - 1)
    # dynamic max for y
    max_y = max([1] + [max(a, b) for _, a, b in points])

    def y_for(v):
        return top_pad + (1.0 - (v / max_y)) * (height - top_pad - bottom_pad)

    def esc(s):
        return s.replace('&','&amp;').replace('<','&lt;').replace('>','&gt;')

    lines = []
    lines.append(f'<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}">')
    lines.append('<style> .t{font:14px sans-serif} .l{font:11px sans-serif; fill:#555} </style>')
    lines.append(f'<text class="t" x="{left_pad}" y="24">{esc(title)}</text>')

    # y grid 0..max_y in 5 steps
    for i in range(6):
        t = max_y * i / 5.0
        y = y_for(t)
        lines.append(f'<line x1="{left_pad}" y1="{y}" x2="{width - right_pad}" y2="{y}" stroke="#eee"/>')
        lines.append(f'<text class="l" x="{left_pad-40}" y="{y+4}">{t:.0f}</text>')

    # build polylines
    pts_a = []
    pts_b = []
    for i, (_, a, b) in enumerate(points):
        x = left_pad + i * x_step
        pts_a.append(f"{x},{y_for(a)}")
        pts_b.append(f"{x},{y_for(b)}")
    lines.append(f'<polyline fill="none" stroke="#1f77b4" stroke-width="2" points="{" ".join(pts_a)}"/>')
    lines.append(f'<polyline fill="none" stroke="#ff7f0e" stroke-width="2" points="{" ".join(pts_b)}"/>')

    # dots and x labels
    for i, (d, a, b) in enumerate(points):
        x = left_pad + i * x_step
        lines.append(f'<circle cx="{x}" cy="{y_for(a)}" r="3" fill="#1f77b4"/>')
        lines.append(f'<circle cx="{x}" cy="{y_for(b)}" r="3" fill="#ff7f0e"/>')
        if i % max(1, n // 8) == 0 or i == n-1:
            lines.append(f'<text class="l" x="{x-20}" y="{height-bottom_pad+14}">{esc(d)}</text>')

    # legend
    lx = left_pad + 10
    ly = top_pad - 10
    lines.append(f'<rect x="{lx}" y="{ly-12}" width="12" height="3" fill="#1f77b4"/>')
    lines.append(f'<text class="l" x="{lx+18}" y="{ly}">{esc(label_a)}</text>')
    lines.append(f'<rect x="{lx+150}" y="{ly-12}" width="12" height="3" fill="#ff7f0e"/>')
    lines.append(f'<text class="l" x="{lx+168}" y="{ly}">{esc(label_b)}</text>')

    lines.append('</svg>')
    with open(out_path, 'w', encoding='utf-8') as f:
        f.write("\n".join(lines))

def main():
    import argparse
    parser = argparse.ArgumentParser(description="Compute monthly coupling (Ca, Ce) for a class from a given PHP file's history")
    parser.add_argument("--file", required=True, help="Path to PHP file to analyze (e.g., src/GraphQLSchemaBuilder.php)")
    parser.add_argument("--class", dest="class_fqn", default=None, help="Optional fully qualified class name override")
    args = parser.parse_args()

    target_file = args.file
    start, end = file_first_last_dates(target_file)
    points = []
    for month_start in month_range(start, end):
        # end of month timestamp
        if month_start.month == 12:
            next_month = datetime(month_start.year + 1, 1, 1)
        else:
            next_month = datetime(month_start.year, month_start.month + 1, 1)
        at = next_month - timedelta(seconds=1)
        date_iso = at.isoformat()
        commit = commit_before(date_iso)
        if not commit or not file_exists_in_commit(commit, target_file):
            commit = commit_before_with_file(date_iso, target_file)
        if not commit:
            continue
        # build graph for PHP files in this commit
        php_files = list_php_files(commit)
        classes = []
        for p in php_files:
            try:
                txt = show_file(commit, p)
            except RuntimeError:
                continue
            classes.extend(parse_php_text(txt, p))
        deps, rev = build_graph(classes)
        # Determine FQN
        try:
            file_text = show_file(commit, target_file)
            file_classes = parse_php_text(file_text, target_file)
        except RuntimeError:
            file_classes = []
        filename_base = os.path.splitext(os.path.basename(target_file))[0]
        target_fqn = pick_fqn_for_file(file_classes, filename_base, args.class_fqn)
        # coupling metrics
        Ca = len(rev.get(target_fqn, set())) if target_fqn else 0
        Ce = len(deps.get(target_fqn, set())) if target_fqn else 0
        points.append((month_start.strftime('%Y-%m'), Ca, Ce))

    # save JSON and SVG
    base = os.path.splitext(os.path.basename(target_file))[0]
    json_path = os.path.join(OUT_DIR, f"{base}_coupling_timeseries.json")
    with open(json_path, 'w', encoding='utf-8') as f:
        json.dump(points, f, indent=2)

    svg_path = os.path.join(OUT_DIR, f"{base}_coupling_timeseries.svg")
    svg_two_series(points, f"{base} Coupling over time", "Ca (fan-in)", "Ce (fan-out)", svg_path)

    print(f"Points: {len(points)}")
    print(f"JSON: {json_path}")
    print(f"SVG:  {svg_path}")

if __name__ == "__main__":
    main()
