# WIKIMIPT (wiki.mipt.tech) sources

В репозитории хранятся:
- Исходники для сборки образа mediawiki (папка mediawiki-build-1.35)
- Роли и плейбуки Ansible для управления сервисом (папка ansible)
- Скрипты и спаршенные данные, используемые для переноса с wikimipt.org (папка utils)

## Локальный запуск и работа

Деплоить и управлять сервисом можно с помощью плейбуков Ansible, они находятся в папке `ansible`. 
После деплоя сервис доступен по адресу http://localhost:8100

### Prerequisites
Для запуска и работы требуется:
- Не меньше 5гб свободного места
- Linux с установленным docker и docker-compose ([инструкция для Ubuntu](https://workshop.samcs.ru/confluence/pages/viewpage.action?pageId=35324146)) или Windows 10 с WSL 2 и docker desktop ([инструкция для Windows](https://workshop.samcs.ru/confluence/pages/viewpage.action?pageId=26706277))
- Python3 и пакетный менеджер pip
- Зависимости python3 (`pip3 install setuptools wheel`)
- Ansible (настоятельно рекомендуется устанавливать через `pip3 install ansible`)
- Питонячие библиотеки для работы с docker и docker-compose (`sudo pip3 install docker==6.1.3 docker-compose`)

### Основные команды

- Задеплоить локально и запустить сервис:

    Этот плейбук создаст нужные папки и файлы, скачает, если нужно, готовый docker-образ и запустит сервис 

    ```bash
    cd ansible
    ansible-playbook deploy.yml -K

    # Ансибл предложит ввести BECOME PASS - это пароль текущего пользователя, чтобы можно было 
    # использовать sudo. Некоторые команды требуют прав суперпользователя
    ```

- Собрать docker-образ mediawiki из исходников:

    ```bash
    cd ansible
    ansible-playbook build.yml  -K
    ```

- Провести миграции Mediawiki (это нужно при первом запуске и при каждой пересборке образа)
    ```bash
    cd ansible
    ansible-playbook update_db.yml -K
    ```
- Установить имя пользователя и пароль администратора (https://www.mediawiki.org/wiki/Manual:CreateAndPromote.php)

    Логин будет: `adminuser` пароль: `adminpassword`
    ```bash
    docker exec -ti wikimipt_mediawiki php maintenance/createAndPromote.php adminuser adminpassword --bureaucrat --sysop --interface-admin --force 
    ```

- Перезапустить сервис
    ```bash
    cd ansible
    ansible-playbook force_restart.yml -K
    ```

- Эти плейбуки можно миксовать, например:

    Собрать образ, запустить сервис с новым образом и провести миграции:

    ```bash
    cd ansible
    ansible-playbook build.yml deploy.yml update_db.yml -K
    ```
