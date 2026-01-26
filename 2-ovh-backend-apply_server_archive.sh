sudo apt install pv

scp -i ~/.ssh/old-staging ubuntu@80.158.61.213:~/staging-server-data-20260121.tar.gz ./
tar -xzf staging-server-data-20260121.tar.gz -C ./staging-server-data-20260121

pg_restore  -h 10.5.2.186   -U postgres  -d peer-old-staging-20260121   --clean --if-exists --no-owner  ./staging-20260121.dump

sudo rsync -av --progress /var/www/peer_beta/peer_backend/runtime-data ./staging_runtime_data_20260121 