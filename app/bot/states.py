from aiogram.fsm.state import State, StatesGroup


class SettingEdit(StatesGroup):
    waiting_value = State()


class SymbolAdd(StatesGroup):
    waiting_symbol = State()


class TemplateEdit(StatesGroup):
    waiting_template = State()


class AdminAdd(StatesGroup):
    waiting_id = State()


class ManualSignal(StatesGroup):
    waiting_symbol = State()


class BacktestRun(StatesGroup):
    waiting_input = State()
