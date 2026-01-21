prod migration
  
! echo all steps 

1. ssh to new backend server throught ssh tunnel 
-  from new backend server ssh to old backend server through ssh tunnel: 
   - make db dump 
   - archive: zip no compression
   - db: pg_dump- env folder
   - runtime data folder 
   - scp from new backend server through ssh tunnel: 
     - get archive 
     - Unarchive it 
     - Copy runtime_data, env to {project_path} 
     - pg_restore dump on db server

implement 1 step in /Users/fcody/Desktop/Peer/peer_backend/docs/modules/tasks/prod-migration/ with ansible playbook project approach. Don't edit this file