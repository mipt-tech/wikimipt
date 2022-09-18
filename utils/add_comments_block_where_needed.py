from getpass import getpass
import MySQLdb

import json

# connecting to the database using 'connect()' method
# it takes 3 required parameters 'host', 'user', 'passwd'
try:
    with MySQLdb.connect(
        host="127.0.0.1",
        port=33336,
        user="mediawiki",
        passwd=getpass("Enter db pass for mediawiki user:"),
        database="mediawiki",
    ) as connection:
        with connection.cursor() as cursor:
            with connection.cursor() as cursor_update:
                get_page_query = 'SELECT * FROM text;'
                cursor.execute(get_page_query)
                page_data = cursor.fetchone()
                while page_data:
                    # print(page_data)
                    page_text = page_data[1].decode()

                    if '{{комментарии}}' not in page_text.replace(' ', '').lower() and (
                            '{{Преподаватель' in page_text or
                            '{{Кафедра' in page_text or
                            '{{Физтех' in page_text or
                            '{{Факультет' in page_text or
                            '{{Предмет' in page_text or
                            '{{Книга' in page_text
                    ) and 'Начните редактировать эту страницу, чтобы увидеть текст шаблона' not in page_text:
                        new_text = page_text + "\n{{комментарии}}"
                        update_page_query = 'UPDATE text SET old_text=%s WHERE old_id=%s;'
                        cursor_update.execute(update_page_query, (new_text, page_data[0] ))
        
                    if int(page_data[0]) // 100 > (int(page_data[0]) - 1) // 100:
                        print(page_data[0])

                    page_data = cursor.fetchone()
                connection.commit()
except Exception as e:
    print(e)
