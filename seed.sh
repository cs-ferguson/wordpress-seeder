#create array of filenames without directory
declare -A POSTS
for FILE in /var/_seeder/*.txt; 
do
#strip extension from file basename to get title
posts_array+=($(basename $FILE) | sed "s/\..*//")
done
# wp post create /var/_seeder/content.txt --post_title='My newer post' --post_status='publish'