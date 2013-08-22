docker ps -q | xargs docker stop
docker ps -q -a | xargs docker rm
docker images | grep -E 'none|b/' | awk '{print $3}' | xargs docker rmi
