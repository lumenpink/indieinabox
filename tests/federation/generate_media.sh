#!/bin/bash
echo ">> Generating dummy media..."
mkdir -p data/media
chmod 777 data/media

docker run --rm -v "$(pwd)/data/media:/data" dpokidov/imagemagick -size 200x200 canvas:"#86b300" -pointsize 80 -fill white -gravity center -draw "text 0,0 'MK'" /data/avatar_misskey.png
docker run --rm -v "$(pwd)/data/media:/data" dpokidov/imagemagick -size 800x400 canvas:"#86b300" -pointsize 80 -fill white -gravity center -draw "text 0,0 'Aaron Misskey'" /data/header_misskey.png

docker run --rm -v "$(pwd)/data/media:/data" dpokidov/imagemagick -size 200x200 canvas:"#6364ff" -pointsize 80 -fill white -gravity center -draw "text 0,0 'MD'" /data/avatar_mastodon.png
docker run --rm -v "$(pwd)/data/media:/data" dpokidov/imagemagick -size 800x400 canvas:"#6364ff" -pointsize 80 -fill white -gravity center -draw "text 0,0 'Aaron Mastodon'" /data/header_mastodon.png

docker run --rm -v "$(pwd)/data/media:/data" dpokidov/imagemagick -size 200x200 canvas:"#ff0055" -pointsize 80 -fill white -gravity center -draw "text 0,0 'PX'" /data/avatar_pixelfed.png
docker run --rm -v "$(pwd)/data/media:/data" dpokidov/imagemagick -size 800x400 canvas:"#ff0055" -pointsize 80 -fill white -gravity center -draw "text 0,0 'Aaron Pixelfed'" /data/header_pixelfed.png

if [ ! -f data/media/dummy.mp4 ]; then
    curl -sL https://github.com/mathiasbynens/small/raw/master/mp4.mp4 -o data/media/dummy.mp4
fi
if [ ! -f data/media/dummy.mp3 ]; then
    curl -sL https://github.com/mathiasbynens/small/raw/master/mp3.mp3 -o data/media/dummy.mp3
fi
chmod -R 777 data/media
