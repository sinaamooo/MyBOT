import os

from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import Application, CommandHandler, CallbackQueryHandler, ContextTypes

# هرگز توکن را داخل کد ننویسید — این مقدار قبلاً در گیت لو رفته بود و باید
# از طریق @BotFather ابطال (revoke) شود.
TOKEN = os.environ["BOT_TOKEN"]

async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    keyboard = [[InlineKeyboardButton("دکمه 1", callback_data='btn1')]]
    await update.message.reply_text("خوش آمدید!", reply_markup=InlineKeyboardMarkup(keyboard))

async def button(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    await query.answer()
    await query.edit_message_text("روی دکمه کلیک شد!")

app = Application.builder().token(TOKEN).build()
app.add_handler(CommandHandler("start", start))
app.add_handler(CallbackQueryHandler(button))
print("ربات روشن شد")
app.run_polling()
