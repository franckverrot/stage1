docker ps -q | xargs docker stop
docker ps -q -a | xargs docker rm
docker images | grep none | awk '{print $3}' | xargs docker rmi
