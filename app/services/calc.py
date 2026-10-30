from __future__ import annotations

import ast
import math
import operator
from decimal import Decimal, DivisionByZero, InvalidOperation, getcontext

getcontext().prec = 40

_BIN = {
    ast.Add: operator.add,
    ast.Sub: operator.sub,
    ast.Mult: operator.mul,
    ast.Div: operator.truediv,
    ast.Mod: operator.mod,
    ast.Pow: operator.pow,
    ast.FloorDiv: operator.floordiv,
}
_UNARY = {ast.UAdd: operator.pos, ast.USub: operator.neg}


class CalcError(Exception):
    pass


def _eval(node) -> Decimal:
    if isinstance(node, ast.Expression):
        return _eval(node.body)
    if isinstance(node, ast.Constant) and isinstance(node.value, (int, float)):
        return Decimal(str(node.value))
    if isinstance(node, ast.BinOp) and type(node.op) in _BIN:
        left, right = _eval(node.left), _eval(node.right)
        if isinstance(node.op, ast.Pow):
            if abs(right) > 1000 or (abs(left) > 1 and float(abs(right)) * math.log10(float(abs(left))) > 400):
                raise CalcError("too large")
        try:
            return _BIN[type(node.op)](left, right)
        except (DivisionByZero, ZeroDivisionError, InvalidOperation) as exc:
            raise CalcError("division by zero") from exc
    if isinstance(node, ast.UnaryOp) and type(node.op) in _UNARY:
        return _UNARY[type(node.op)](_eval(node.operand))
    raise CalcError("unsupported")


def evaluate(expr: str) -> Decimal:
    try:
        tree = ast.parse(expr, mode="eval")
    except SyntaxError as exc:
        raise CalcError("syntax") from exc
    return _eval(tree)


def format_result(value: Decimal) -> str:
    if value == value.to_integral_value() and abs(value) < Decimal("1e30"):
        return f"{int(value):,}"
    if abs(value) >= Decimal("1e30"):
        return f"{value:.6E}"
    text = f"{value:,.10f}".rstrip("0").rstrip(".")
    return text
