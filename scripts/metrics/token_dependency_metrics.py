#!/usr/bin/env python3
import os
import re
import json
from collections import defaultdict, namedtuple

# This script scans PHP files to build a simple dependency graph at class level.
# It computes Martin metrics for each class (approximated):
# - Abstractness A: 1 if abstract/interface, else 0
# - Instability I: Ce / (Ca + Ce) where
#    Ce = number of distinct classes this class depends on (via `use` or FQCN refs)
#    Ca = number of distinct classes that depend on this class
# - Distance D: |A + I - 1|
# It then filters to token system classes (by name/path heuristics) and outputs
# a JSON report and an SVG bar chart of D.

SRC_ROOT = os.path.join(os.getcwd(), "src")
OUT_DIR = os.path.join(os.getcwd(), "scripts", "metrics", "out")
os.makedirs(OUT_DIR, exist_ok=True)

PhpClass = namedtuple("PhpClass", [
    "name",         # Fully qualified name Namespace\\Class
    "short",        # Class
    "namespace",    # Namespace
    "path",         # File path
    "is_abstract",  # bool
    "is_interface", # bool
    "uses"          # set of fully qualified or relative names referenced via `use`
])

NAMESPACE_RE = re.compile(r"^\s*namespace\s+([^;]+);", re.MULTILINE)
CLASS_RE = re.compile(r"^\s*(abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)", re.MULTILINE)
INTERFACE_RE = re.compile(r"^\s*interface\s+([A-Za-z_][A-Za-z0-9_]*)", re.MULTILINE)
USE_RE = re.compile(r"^\s*use\s+([^;]+);", re.MULTILINE)

def parse_php_file(path):
    try:
        with open(path, 'r', encoding='utf-8', errors='ignore') as f:
            text = f.read()
    except Exception:
        return []

    ns_match = NAMESPACE_RE.search(text)
    namespace = ns_match.group(1).strip() if ns_match else ""

    classes = []

    # Interfaces
    for m in INTERFACE_RE.finditer(text):
        cls = m.group(1)
        fqn = (namespace + "\\" + cls) if namespace else cls
        classes.append(PhpClass(
            name=fqn,
            short=cls,
            namespace=namespace,
            path=path,
            is_abstract=False,
            is_interface=True,
            uses=set(),
        ))

    # Classes
    for m in CLASS_RE.finditer(text):
        is_abs = bool(m.group(1))
        cls = m.group(2)
        fqn = (namespace + "\\" + cls) if namespace else cls
        classes.append(PhpClass(
            name=fqn,
            short=cls,
            namespace=namespace,
            path=path,
            is_abstract=is_abs,
            is_interface=False,
            uses=set(),
        ))

    # Collect use statements (may include multiple separated by commas, but in PHP they are per line typically)
    uses = set()
    for m in USE_RE.finditer(text):
        raw = m.group(1)
        # Handle group use statements and multiple aliases
        # Split by comma not within braces
        parts = []
        if '{' in raw and '}' in raw:
            # e.g., use Foo\\Bar\\{Baz, Qux as Alias};
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

    # Attach uses to each class found in this file
    if classes:
        classes = [c._replace(uses=set(uses)) for c in classes]

    return classes

def scan_php_classes(root):
    all_classes = []
    for dirpath, _, filenames in os.walk(root):
        for fn in filenames:
            if not fn.endswith('.php'):
                continue
            path = os.path.join(dirpath, fn)
            all_classes.extend(parse_php_file(path))
    return all_classes

def build_dependency_graph(classes):
    # Map short and FQCN to FQCN for resolution
    name_to_fqn = {}
    for c in classes:
        name_to_fqn[c.name] = c.name
        name_to_fqn[c.short] = c.name

    deps = defaultdict(set)  # class FQN -> set of class FQN it depends on

    for c in classes:
        for u in c.uses:
            # Try to resolve to known classes by FQCN or short name
            target = None
            if u in name_to_fqn:
                target = name_to_fqn[u]
            else:
                # If fully qualified but not found, try last segment as short name
                short = u.split('\\')[-1]
                target = name_to_fqn.get(short)
            if target and target != c.name:
                deps[c.name].add(target)

    # Ensure all nodes exist
    for c in classes:
        deps.setdefault(c.name, set())

    # Compute reverse dependencies (Ca)
    rev = defaultdict(set)  # class -> classes that depend on it
    for src, outs in deps.items():
        for dst in outs:
            rev[dst].add(src)
    for c in classes:
        rev.setdefault(c.name, set())

    return deps, rev

def compute_metrics(classes, deps, rev):
    metrics = {}
    for c in classes:
        Ce = len(deps.get(c.name, ()))
        Ca = len(rev.get(c.name, ()))
        I = Ce / (Ca + Ce) if (Ca + Ce) > 0 else 0.0
        A = 1.0 if (c.is_interface or c.is_abstract) else 0.0
        D = abs(A + I - 1.0)
        metrics[c.name] = {
            "A": A,
            "I": I,
            "D": D,
            "Ca": Ca,
            "Ce": Ce,
            "path": c.path,
            "short": c.short,
            "namespace": c.namespace,
        }
    return metrics

TOKEN_KEYWORDS = [
    "token", "wallet", "transaction", "mcap", "liquidity", "jwt", "gem"
]

def is_token_related(entry):
    s = (entry[0] + " " + entry[1]["path"]).lower()
    return any(k in s for k in TOKEN_KEYWORDS)

def save_json(path, data):
    with open(path, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2)

def generate_svg_bar_chart(items, title, out_path):
    # items: list of tuples (label, value)
    width = 900
    bar_height = 24
    gap = 10
    left_pad = 260
    right_pad = 30
    top_pad = 50
    bottom_pad = 40
    n = len(items)
    height = top_pad + bottom_pad + n * (bar_height + gap)

    max_val = 1.0  # D in [0,1]
    x_scale = (width - left_pad - right_pad) / max_val

    def esc(s):
        return (s.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;'))

    lines = []
    lines.append(f'<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}">')
    lines.append(f'<style> .lbl {{ font: 12px sans-serif; fill: #333 }} .title {{ font: 16px sans-serif; font-weight: bold; }} .val {{ font: 12px monospace; fill: #000 }} </style>')
    lines.append(f'<text class="title" x="{left_pad}" y="24">{esc(title)}</text>')
    # axis
    axis_y = top_pad - 10
    for t in [0.0, 0.25, 0.5, 0.75, 1.0]:
        x = left_pad + t * x_scale
        lines.append(f'<line x1="{x}" y1="{axis_y}" x2="{x}" y2="{height - bottom_pad}" stroke="#ddd"/>')
        lines.append(f'<text class="lbl" x="{x-6}" y="{axis_y-5}">{t:.2f}</text>')

    y = top_pad
    for label, val in items:
        bar_w = max(0, min(val, max_val)) * x_scale
        lines.append(f'<text class="lbl" x="10" y="{y + bar_height - 6}">{esc(label)}</text>')
        lines.append(f'<rect x="{left_pad}" y="{y}" width="{bar_w}" height="{bar_height}" fill="#6baed6"/>')
        lines.append(f'<text class="val" x="{left_pad + bar_w + 6}" y="{y + bar_height - 6}">{val:.3f}</text>')
        y += bar_height + gap

    lines.append('</svg>')
    with open(out_path, 'w', encoding='utf-8') as f:
        f.write("\n".join(lines))

def main():
    classes = scan_php_classes(SRC_ROOT)
    deps, rev = build_dependency_graph(classes)
    metrics = compute_metrics(classes, deps, rev)

    # Filter token-related classes
    token_entries = [(name, m) for name, m in metrics.items() if is_token_related((name, m))]
    # Sort by D desc
    token_entries.sort(key=lambda kv: kv[1]["D"], reverse=True)

    # Save JSON
    json_path = os.path.join(OUT_DIR, "token_class_metrics.json")
    save_json(json_path, {k: v for k, v in token_entries})

    # Prepare items for plot (label = short name + file)
    labels = []
    for name, m in token_entries:
        short = name.split('\\')[-1]
        fn = os.path.basename(m["path"])
        labels.append(f"{short} ({fn})")

    items = list(zip(labels, [m["D"] for _, m in token_entries]))
    svg_path = os.path.join(OUT_DIR, "token_class_D.svg")
    generate_svg_bar_chart(items, "Token System Classes: D = |A + I - 1|", svg_path)

    # Also print a brief table to stdout for quick viewing
    print("Computed metrics for", len(token_entries), "token-related classes")
    for name, m in token_entries[:30]:
        print(f"{name}: A={m['A']:.2f} I={m['I']:.2f} D={m['D']:.3f} Ca={m['Ca']} Ce={m['Ce']} :: {m['path']}")
    print(f"JSON: {json_path}")
    print(f"SVG:  {svg_path}")

if __name__ == "__main__":
    main()

