from getpass import getpass
import MySQLdb

import json
import transliterate


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
            with open("wikimipt_comments.json", "r") as f:
                comments = json.load(f)
                for page_title in comments.keys():
                    print(page_title)

                    page: str = page_title.replace(' ', '_')
                    get_page_query = f'SELECT * FROM page WHERE page_title="{page}" LIMIT 1;'
                    cursor.execute(get_page_query)
                    page_data = cursor.fetchone()
                    page_id = int(page_data[0])
                    print(page_data, page_id)

                    latest_non_reply_comment_id = -1

                    for comment in comments[page_title]:
                        author_ip = '10.255.1.1'
                        actor_id = 0
                        comment_text = comment['text']
                        comment_date = f'{comment["date"]} {comment["time"]}'
                        comment_parent_id = 0

                        if comment['author'].startswith('Непредставившийся участник #'):
                            anon_user_num = int(comment['author'][28:])
                            author_ip = f"10.{page_id // 128}.{page_id % 128 + anon_user_num // 256}.{anon_user_num % 256}"  # Creating unique ips for each unique commentator
                        else:
                            actor_name = f'imported>{comment["author"]}'
                            get_actor_query = 'SELECT * FROM actor WHERE actor_name=%s LIMIT 1;'
                            cursor.execute(get_actor_query, (actor_name, ))
                            actor_data = cursor.fetchone()

                            print(actor_data)
                            if actor_data is None:
                                set_actor_query = 'INSERT INTO actor (actor_name) VALUES (%s);'
                                cursor.execute(set_actor_query, (actor_name, ))
                                actor_id = cursor.lastrowid
                            else:
                                actor_id = int(actor_data[0])

                        if comment['reply'] and latest_non_reply_comment_id != -1:
                            comment_parent_id = latest_non_reply_comment_id
                        
                        insert_comment_query: str = 'INSERT INTO Comments (Comment_Page_ID, Comment_actor, Comment_Text, Comment_Date, Comment_Parent_ID, Comment_IP) VALUES (%s, %s, %s, %s, %s, %s)'
                        print((page_id, actor_id, comment_text, comment_date, comment_parent_id, author_ip))
                        cursor.execute(insert_comment_query, (page_id, actor_id, comment_text, comment_date, comment_parent_id, author_ip))
                        new_comment_id = cursor.lastrowid

                        if not comment['reply']:
                            latest_non_reply_comment_id = new_comment_id

                        if comment['score'] is not None:
                            comment_score = int(comment['score'])
                            if comment_score != 0:
                                insert_vote_query: str = 'INSERT INTO Comments_Vote (Comment_Vote_ID, Comment_Vote_actor, Comment_Vote_Score, Comment_Vote_Date, Comment_Vote_IP) VALUES (%s, %s, %s, %s, %s)'
                                cursor.execute(insert_vote_query, (new_comment_id, 0, comment_score, f'2022-09-16 23:59:59', '10.0.0.1'))

                    connection.commit()
except Exception as e:
    print(e)
