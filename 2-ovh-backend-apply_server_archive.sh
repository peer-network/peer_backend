echo "Installing pv..."
sudo apt install pv

#echo "Downloading server archive..."
#scp -r -i ./old-staging-ssh-key ubuntu@80.158.61.213:~/staging-server-data-202601260837 ./
echo "Extracting runtime-data..."
sudo tar -xf ./staging-server-data-202601260837/staging-server-data-202601260837.tar -C ./staging-server-data-202601260837

#echo "Creating database..."
#psql -h 192.168.1.210 -U postgres -tc "SELECT 1 FROM pg_database WHERE datname='staging-server-data-202601260837'" | grep -q 1 || psql -h 192.168.1.210 -U postgres -c "CREATE DATABASE \"staging-server-data-202601260837\""
#echo "Restoring database..."
#pg_restore  -h 192.168.1.210 -U postgres  -d staging-server-data-202601260837    --clean --if-exists --no-owner --no-privileges  ./staging-server-data-202601260837/staging-202601260837.dump

echo "Syncing runtime-data..."
sudo mkdir /var/www/peer_backend/runtime-data-202601260837
sudo rsync -av --progress staging-server-data-202601260837/var/www/peer_beta/peer_backend/runtime-data/ /var/www/peer_backend/runtime-data-202601260837
