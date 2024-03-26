script_dir=$(cd -P -- "$(dirname -- "$0")" && pwd -P)
#project_dir=$(dirname -- "$script_dir")
project_dir=$script_dir
echo $project_dir
cd -P -- "$project_dir"

files_to_encrypt=""
for f in $(find ./inventories/ -type f -regextype posix-egrep -regex ".*(prod|stage|stg).*"); do
    echo $f
    if head -n1 "$f" | grep -q "\$ANSIBLE_VAULT;1.1;AES256"; then
        echo "Encrypted"
        # File seems already encrypted
    else
        files_to_encrypt="$files_to_encrypt $f"
    fi
done;

if [ -n "$files_to_encrypt" ]; then
    ansible-vault encrypt $files_to_encrypt
else
    echo "All already encrypted, nothing to do"
fi
