"""Builds docs/report/Chapters_4-6.docx from ch4.py, ch5.py and ch6.py."""
import os
import sys
from docbuilder import Builder
from ch4 import CH4
from ch5 import CH5
from ch6 import CH6

out = sys.argv[1] if len(sys.argv) > 1 else os.path.join(os.path.dirname(__file__), "..", "Chapters_4-6.docx")
b = Builder(4)
b.add(CH4)
b.add(CH5)
b.add(CH6)
b.save(out)
print("saved", os.path.abspath(out))
