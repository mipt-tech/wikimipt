script_dir=$(cd -P -- "$(dirname -- "$0")" && pwd -P)
#project_dir=$(dirname -- "$script_dir")
project_dir=$script_dir
echo $project_dir
cd -P -- "$project_dir"

files_to_decrypt=""
for f in $(find ./inventories/ -type f -regextype posix-egrep -regex ".*(prod|stage|stg).*"); do
    echo $f
    if head -n1 "$f" | grep -q "\$ANSIBLE_VAULT;1.1;AES256"; then
        files_to_decrypt="$files_to_decrypt $f"
    else
        # File seems already decrypted
        echo "Already decrypted"
    fi
done;

if [ -n "$files_to_decrypt" ]; then
    ansible-vault decrypt $files_to_decrypt
else
    echo "All already decrypted, nothing to do"
fi
