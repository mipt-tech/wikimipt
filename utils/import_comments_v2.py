from getpass import getpass
import MySQLdb

import json
import re

def is_valid_ipv4(ip):
    """Validates IPv4 addresses.
    """
    pattern = re.compile(r"""
        ^
        (?:
          # Dotted variants:
          (?:
            # Decimal 1-255 (no leading 0's)
            [3-9]\d?|2(?:5[0-5]|[0-4]?\d)?|1\d{0,2}
          |
            0x0*[0-9a-f]{1,2}  # Hexadecimal 0x0 - 0xFF (possible leading 0's)
          |
            0+[1-3]?[0-7]{0,2} # Octal 0 - 0377 (possible leading 0's)
          )
          (?:                  # Repeat 0-3 times, separated by a dot
            \.
            (?:
              [3-9]\d?|2(?:5[0-5]|[0-4]?\d)?|1\d{0,2}
            |
              0x0*[0-9a-f]{1,2}
            |
              0+[1-3]?[0-7]{0,2}
            )
          ){0,3}
        |
          0x0*[0-9a-f]{1,8}    # Hexadecimal notation, 0x0 - 0xffffffff
        |
          0+[0-3]?[0-7]{0,10}  # Octal notation, 0 - 037777777777
        |
          # Decimal notation, 1-4294967295:
          429496729[0-5]|42949672[0-8]\d|4294967[01]\d\d|429496[0-6]\d{3}|
          42949[0-5]\d{4}|4294[0-8]\d{5}|429[0-3]\d{6}|42[0-8]\d{7}|
          4[01]\d{8}|[1-3]\d{0,9}|[4-9]\d{0,8}
        )
        $
    """, re.VERBOSE | re.IGNORECASE)
    return pattern.match(ip) is not None



# connecting to the database using 'connect()' method
# it takes 3 required parameters 'host', 'user', 'passwd'
try:
    with open("comments_v2.json", "r") as f:
        comments_v2 = json.load(f)
    with open("comments_vote_v2.json", "r") as f:
        comments_vote_v2 = json.load(f)
    with open("page_v2.json", "r") as f:
        page_v2 = json.load(f)
    with open("users_v2.json", "r") as f:
        users_v2 = json.load(f)

    users = {}
    for user in users_v2:
        users[user['user_id']] = user

    pages = {}
    for page in page_v2:
        pages[page['page_id']] = page

    comments_old_id_to_new_id_mapping = {}

    with MySQLdb.connect(
        host="127.0.0.1",
        port=33336,
        user="mediawiki",
        passwd=getpass("Enter db pass for mediawiki user:"),
        database="mediawiki",
    ) as connection:
        with connection.cursor() as cursor:
            for comment in comments_v2:
                if not comment['Comment_Page_ID'] in pages.keys():
                    print('Failed to fetch page for comment:', comment)
                    continue
                page = pages[comment['Comment_Page_ID']]
                page_title = page['page_title']
                print(page_title)
                page: str = page_title.replace(' ', '_')
                get_page_query = f'SELECT * FROM page WHERE page_title="{page}" LIMIT 1;'
                cursor.execute(get_page_query)
                page_data = cursor.fetchone()
                page_id = int(page_data[0])

                author_ip = comment['Comment_IP']
                actor_id = 0
                comment_text = comment['Comment_Text']
                comment_date = comment['Comment_Date']
                comment_parent_id = comment['Comment_Parent_ID']
                comment_hidden = comment['Comment_Hidden']
                comment_deleted = comment['Comment_Deleted']

                if comment['Comment_user_id'] != 0:
                    user = users[comment['Comment_user_id']]
                    actor_name = f'imported>{user["user_name"]}'
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
                else:
                    author_ip = comment['Comment_Username'][:40]

                if comment['Comment_Parent_ID'] != 0:
                    comment_parent_id = comments_old_id_to_new_id_mapping[comment['Comment_Parent_ID']]

                insert_comment_query: str = 'INSERT INTO Comments (Comment_Page_ID, Comment_actor, Comment_Text, Comment_Date, Comment_Parent_ID, Comment_IP, Comment_hidden, Comment_deleted) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)'
                print((page_id, actor_id, comment_text, comment_date, comment_parent_id, author_ip))
                cursor.execute(insert_comment_query, (page_id, actor_id, comment_text, comment_date, comment_parent_id, author_ip, comment_hidden, comment_deleted))
                new_comment_id = cursor.lastrowid

                comments_old_id_to_new_id_mapping[comment['CommentID']] = new_comment_id

                comment_score = comment['Comment_Plus_Count'] - comment['Comment_Minus_Count']
                if comment_score != 0:
                    insert_vote_query: str = 'INSERT INTO Comments_Vote (Comment_Vote_ID, Comment_Vote_actor, Comment_Vote_Score, Comment_Vote_Date, Comment_Vote_IP) VALUES (%s, %s, %s, %s, %s)'
                    cursor.execute(insert_vote_query, (new_comment_id, 0, comment_score, f'2022-09-16 23:59:59', '10.0.0.1'))
            connection.commit()
except Exception as e:
    print(e)
