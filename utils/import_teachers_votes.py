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
            with open("vote_lists.json", "r") as f:
                vote_lists = json.load(f)
                for key in vote_lists.keys():
                    print(key)

                    page: str = key.replace(' ', '_')
                    get_page_query = f'SELECT * FROM page WHERE page_title="{page}" LIMIT 1;'
                    cursor.execute(get_page_query)
                    page_data = cursor.fetchone()
                    page_id = page_data[0]
                    print(page_data, page_id)

                    insert_vote_query: str = 'INSERT INTO ratepage_vote (rv_page_id, rv_user, rv_answer, rv_date, rv_contest) VALUES'
                    randuser_num = 1

                    knowledge_votes = vote_lists[key]["knowledge"]["vote_list"]
                    for vote in knowledge_votes:
                        insert_vote_query += f' ({page_id}, "imported_randuser_{randuser_num}", {vote}, "2022-08-20 18:00:00", "expert"),'
                        randuser_num += 1

                    teaching_ability_votes = vote_lists[key]["teaching_ability"]["vote_list"]
                    for vote in teaching_ability_votes:
                        insert_vote_query += f' ({page_id}, "imported_randuser_{randuser_num}", {vote}, "2022-08-20 18:00:00", "instructor"),'
                        randuser_num += 1

                    communication_votes = vote_lists[key]["communication"]["vote_list"]
                    for vote in communication_votes:
                        insert_vote_query += f' ({page_id}, "imported_randuser_{randuser_num}", {vote}, "2022-08-20 18:00:00", "communication"),'
                        randuser_num += 1

                    freebie_votes = vote_lists[key]["freebie"]["vote_list"]
                    for vote in freebie_votes:
                        insert_vote_query += f' ({page_id}, "imported_randuser_{randuser_num}", {vote}, "2022-08-20 18:00:00", "freebie"),'
                        randuser_num += 1

                    total_votes = vote_lists[key]["overall_score"]["vote_list"]
                    for vote in total_votes:
                        insert_vote_query += f' ({page_id}, "imported_randuser_{randuser_num}", {vote}, "2022-08-20 18:00:00", "total"),'
                        randuser_num += 1

                    # Skipping query if no data need to insert for current teacher
                    if randuser_num == 1:
                        continue

                    insert_vote_query = ';'.join(insert_vote_query.rsplit(insert_vote_query[-1:], 1))

                    print(insert_vote_query)
                    cursor.execute(insert_vote_query)
                    connection.commit()
except Exception as e:
    print(e)
