"""Small drawing toolkit for classic UML / ERD figures (matplotlib, y axis pointing down).

Coordinates are in "units" where 100 units = 1 inch, so a 10 pt font is about 14 units tall.
"""
import math
import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import Rectangle, FancyBboxPatch, Ellipse, Circle, Polygon

FONT = "Arial"
LINE = "#1a1a1a"
CLASS_FILL = "#fdfbe8"     # pale yellow, like StarUML / Visio class shapes
ENTITY_HEAD = "#dce6f2"    # pale blue header for database tables
ACTION_FILL = "#eef3fa"
NOTE_FILL = "#fff8d6"
CHAR_W = 6.1               # average width of one 9 pt character in units


class Canvas:
    def __init__(self, width, height):
        self.w, self.h = width, height
        self.fig = plt.figure(figsize=(width / 100, height / 100))
        self.ax = self.fig.add_axes([0, 0, 1, 1])
        self.ax.set_xlim(0, width)
        self.ax.set_ylim(height, 0)
        self.ax.axis("off")

    # ---- basics -----------------------------------------------------------
    def text(self, x, y, s, size=9, weight="normal", ha="center", va="center", style="normal", color=LINE, rotation=0, bg=None):
        kw = {}
        if bg:
            kw["bbox"] = dict(boxstyle="square,pad=0.15", fc=bg, ec="none")
        self.ax.text(x, y, s, fontsize=size, fontfamily=FONT, fontweight=weight, ha=ha, va=va,
                     fontstyle=style, color=color, rotation=rotation, zorder=6, **kw)

    def line(self, pts, dashed=False, lw=1.0, color=LINE, z=2):
        xs, ys = zip(*pts)
        self.ax.plot(xs, ys, color=color, lw=lw, ls=(0, (5, 3)) if dashed else "-", zorder=z, solid_capstyle="butt")

    def rect(self, x, y, w, h, fill="white", lw=1.0, round_=0, dashed=False, z=3):
        if round_:
            p = FancyBboxPatch((x, y), w, h, boxstyle=f"round,pad=0,rounding_size={round_}", fc=fill, ec=LINE, lw=lw,
                               ls=(0, (5, 3)) if dashed else "-", zorder=z)
        else:
            p = Rectangle((x, y), w, h, fc=fill, ec=LINE, lw=lw, ls=(0, (5, 3)) if dashed else "-", zorder=z)
        self.ax.add_patch(p)

    # ---- arrow heads ------------------------------------------------------
    def head(self, tip, frm, kind="open", size=11):
        """kind: open (V), filled (solid triangle), hollow (generalisation triangle)."""
        dx, dy = tip[0] - frm[0], tip[1] - frm[1]
        d = math.hypot(dx, dy) or 1
        ux, uy = dx / d, dy / d
        px, py = -uy, ux
        half = size * (0.45 if kind == "open" else 0.55)
        base = (tip[0] - ux * size, tip[1] - uy * size)
        a = (base[0] + px * half, base[1] + py * half)
        b = (base[0] - px * half, base[1] - py * half)
        if kind == "open":
            self.line([a, tip, b])
        else:
            self.ax.add_patch(Polygon([tip, a, b], closed=True, fc=LINE if kind == "filled" else "white", ec=LINE, lw=1, zorder=5))

    def arrow(self, pts, kind="open", dashed=False, label=None, label_at=None, label_offset=(0, -8), size=11):
        pts = list(pts)
        if kind == "hollow":
            # stop the line at the triangle base so it does not show through
            tip, frm = pts[-1], pts[-2]
            dx, dy = tip[0] - frm[0], tip[1] - frm[1]
            d = math.hypot(dx, dy) or 1
            pts[-1] = (tip[0] - dx / d * size, tip[1] - dy / d * size)
            self.line(pts, dashed)
            self.head(tip, frm, kind, size)
        else:
            self.line(pts, dashed)
            self.head(pts[-1], pts[-2], kind, size)
        if label:
            if label_at is None:
                i = len(pts) // 2
                a, b = pts[i - 1], pts[i]
                label_at = ((a[0] + b[0]) / 2 + label_offset[0], (a[1] + b[1]) / 2 + label_offset[1])
            self.text(*label_at, label, size=8, style="italic", bg="white")

    # ---- UML shapes ---------------------------------------------------------
    def actor(self, x, y, name, scale=1.0):
        s = scale
        self.ax.add_patch(Circle((x, y), 9 * s, fc="white", ec=LINE, lw=1.1, zorder=4))
        self.line([(x, y + 9 * s), (x, y + 38 * s)], lw=1.1)
        self.line([(x - 17 * s, y + 18 * s), (x + 17 * s, y + 18 * s)], lw=1.1)
        self.line([(x - 15 * s, y + 60 * s), (x, y + 38 * s), (x + 15 * s, y + 60 * s)], lw=1.1)
        for i, part in enumerate(name.split("\n")):
            self.text(x, y + 72 * s + i * 13, part, size=9, weight="bold")

    def usecase(self, x, y, label, w=None, h=40):
        lines = label.split("\n")
        w = w or max(len(l) for l in lines) * CHAR_W + 34
        self.ax.add_patch(Ellipse((x, y), w, h, fc="white", ec=LINE, lw=1, zorder=3))
        for i, l in enumerate(lines):
            self.text(x, y + (i - (len(lines) - 1) / 2) * 12, l, size=8.5)
        return {"x": x, "y": y, "w": w, "h": h}

    def ellipse_point(self, uc, toward):
        """Point on a use-case ellipse border in the direction of another point."""
        dx, dy = toward[0] - uc["x"], toward[1] - uc["y"]
        a, b = uc["w"] / 2, uc["h"] / 2
        t = 1 / math.sqrt((dx / a) ** 2 + (dy / b) ** 2) if (dx or dy) else 0
        return (uc["x"] + dx * t, uc["y"] + dy * t)

    def class_box(self, x, y, name, attrs, methods, stereotype=None, min_w=150):
        lines = [name] + attrs + methods + ([f"«{stereotype}»"] if stereotype else [])
        w = max(min_w, max(len(l) for l in lines) * CHAR_W + 18)
        lh = 13
        head_h = 22 + (12 if stereotype else 0)
        a_h = max(1, len(attrs)) * lh + 8
        m_h = max(1, len(methods)) * lh + 8
        h = head_h + a_h + m_h
        self.rect(x, y, w, h, CLASS_FILL, lw=1.1)
        self.line([(x, y + head_h), (x + w, y + head_h)])
        self.line([(x, y + head_h + a_h), (x + w, y + head_h + a_h)])
        if stereotype:
            self.text(x + w / 2, y + 11, f"«{stereotype}»", size=8, style="italic")
            self.text(x + w / 2, y + 25, name, size=9.5, weight="bold")
        else:
            self.text(x + w / 2, y + head_h / 2, name, size=9.5, weight="bold")
        for i, a in enumerate(attrs):
            self.text(x + 8, y + head_h + 10 + i * lh, a, size=8.5, ha="left")
        for i, m in enumerate(methods):
            self.text(x + 8, y + head_h + a_h + 10 + i * lh, m, size=8.5, ha="left")
        return {"x": x, "y": y, "w": w, "h": h}

    def entity(self, x, y, name, columns, w=None):
        """columns: list of (key, name, type) where key is 'PK', 'FK', 'PK,FK', 'UQ' or ''."""
        rh = 18
        w = w or max(len(c[1]) + len(c[2]) for c in columns) * CHAR_W + 70
        w = max(w, len(name) * 8 + 30)
        h = 26 + rh * len(columns) + 4
        self.rect(x, y, w, h, "white", lw=1.2)
        self.ax.add_patch(Rectangle((x, y), w, 26, fc=ENTITY_HEAD, ec=LINE, lw=1.2, zorder=3))
        self.text(x + w / 2, y + 13, name, size=9.5, weight="bold")
        self.line([(x + 34, y + 26), (x + 34, y + h)], lw=0.6, color="#777777")
        for i, (key, col, typ) in enumerate(columns):
            cy = y + 26 + 11 + i * rh
            if key:
                self.text(x + 17, cy, key, size=7, weight="bold", color="#7a2e00" if "PK" in key else "#1f4e79")
            self.text(x + 41, cy, col, size=8.5, ha="left", weight="bold" if "PK" in key else "normal")
            self.text(x + w - 8, cy, typ, size=7.5, ha="right", color="#555555")
        return {"x": x, "y": y, "w": w, "h": h}

    def crow_end(self, point, toward, kind):
        """Crow's foot marker at `point` on an entity edge; `toward` is the next point along the line.
        kind: 'one' (||), 'zero_one' (o|), 'many' (|<), 'zero_many' (o<)."""
        dx, dy = toward[0] - point[0], toward[1] - point[1]
        d = math.hypot(dx, dy) or 1
        ux, uy = dx / d, dy / d
        px, py = -uy, ux
        at = lambda dist, off=0: (point[0] + ux * dist + px * off, point[1] + uy * dist + py * off)
        if kind in ("many", "zero_many"):
            self.line([at(0, 8), at(13, 0), at(0, -8)], lw=1.1)
            self.line([at(13, 8), at(13, -8)], lw=1.1) if kind == "many" else None
            if kind == "zero_many":
                c = at(19)
                self.ax.add_patch(Circle(c, 5, fc="white", ec=LINE, lw=1.1, zorder=5))
        else:
            self.line([at(7, 7), at(7, -7)], lw=1.1)
            if kind == "one":
                self.line([at(12, 7), at(12, -7)], lw=1.1)
            else:
                c = at(17)
                self.ax.add_patch(Circle(c, 5, fc="white", ec=LINE, lw=1.1, zorder=5))

    def relation(self, pts, start_kind, end_kind, label=None, label_pos=None):
        self.line(pts, lw=1.1)
        self.crow_end(pts[0], pts[1], start_kind)
        self.crow_end(pts[-1], pts[-2], end_kind)
        if label:
            self.text(*label_pos, label, size=7.5, style="italic", bg="white")

    # ---- sequence diagram -------------------------------------------------
    def lifeline(self, x, top, bottom, name, kind="object", width=None):
        if kind == "actor":
            self.actor(x, top - 2, name, scale=0.8)
            start = top + 72
        else:
            w = width or max(len(l) for l in name.split("\n")) * 6.8 + 24
            self.rect(x - w / 2, top, w, 40, CLASS_FILL, lw=1.1)
            for i, part in enumerate(name.split("\n")):
                self.text(x, top + 20 + (i - (len(name.split("\n")) - 1) / 2) * 12, part, size=9, weight="bold")
            start = top + 40
        self.line([(x, start), (x, bottom)], dashed=True, lw=0.8, color="#555555", z=1)

    def activation(self, x, y1, y2):
        self.rect(x - 5, y1, 10, y2 - y1, "white", lw=1, z=4)

    def message(self, x1, x2, y, label, ret=False, num=None):
        text = f"{num}: {label}" if num else label
        if x1 == x2:  # message to self
            self.line([(x1 + 5, y), (x1 + 40, y), (x1 + 40, y + 16)], lw=1)
            self.arrow([(x1 + 40, y + 16), (x1 + 6, y + 16)], kind="filled", size=8)
            self.text(x1 + 46, y + 8, text, size=8, ha="left", bg="white")
            return
        start = x1 + (5 if x2 > x1 else -5)
        end = x2 - (5 if x2 > x1 else -5)
        if ret:
            self.arrow([(start, y), (end, y)], kind="open", dashed=True, size=9)
        else:
            self.arrow([(start, y), (end, y)], kind="filled", size=9)
        self.text((x1 + x2) / 2, y - 8, text, size=8, bg="white")

    def fragment(self, x, y, w, h, op, guard=None, dividers=()):
        self.rect(x, y, w, h, "none", lw=1, z=1)
        tag_w = len(op) * 8 + 16
        self.ax.add_patch(Polygon([(x, y), (x + tag_w, y), (x + tag_w, y + 12), (x + tag_w - 8, y + 20), (x, y + 20)],
                                  closed=True, fc="white", ec=LINE, lw=1, zorder=2))
        self.text(x + 6, y + 10, op, size=8.5, weight="bold", ha="left")
        if guard:
            self.text(x + tag_w + 8, y + 12, guard, size=8, ha="left", style="italic", bg="white")
        for dy, g in dividers:
            self.line([(x, y + dy), (x + w, y + dy)], dashed=True, lw=0.9, z=1)
            self.text(x + 10, y + dy + 12, g, size=8, ha="left", style="italic", bg="white")

    # ---- activity diagram -------------------------------------------------
    def start(self, x, y):
        self.ax.add_patch(Circle((x, y), 9, fc=LINE, ec=LINE, zorder=4))

    def end(self, x, y):
        self.ax.add_patch(Circle((x, y), 11, fc="white", ec=LINE, lw=1.3, zorder=4))
        self.ax.add_patch(Circle((x, y), 6.5, fc=LINE, ec=LINE, zorder=5))

    def action(self, x, y, label, w=None, h=None):
        lines = label.split("\n")
        w = w or max(len(l) for l in lines) * CHAR_W + 28
        h = h or 16 + 13 * len(lines)
        self.rect(x - w / 2, y - h / 2, w, h, ACTION_FILL, lw=1.1, round_=10)
        for i, l in enumerate(lines):
            self.text(x, y + (i - (len(lines) - 1) / 2) * 13, l, size=8.5)
        return {"x": x, "y": y, "w": w, "h": h}

    def decision(self, x, y, size=18):
        self.ax.add_patch(Polygon([(x, y - size), (x + size, y), (x, y + size), (x - size, y)], closed=True,
                                  fc="white", ec=LINE, lw=1.1, zorder=4))

    def note(self, x, y, w, text):
        lines = text.split("\n")
        h = 12 + 12 * len(lines)
        fold = 9
        self.ax.add_patch(Polygon([(x, y), (x + w - fold, y), (x + w, y + fold), (x + w, y + h), (x, y + h)], closed=True,
                                  fc=NOTE_FILL, ec=LINE, lw=0.9, zorder=3))
        self.line([(x + w - fold, y), (x + w - fold, y + fold), (x + w, y + fold)], lw=0.9)
        for i, l in enumerate(lines):
            self.text(x + 7, y + 12 + i * 12, l, size=8, ha="left")

    def save(self, path, dpi=220):
        self.fig.savefig(path, dpi=dpi, facecolor="white")
        plt.close(self.fig)
