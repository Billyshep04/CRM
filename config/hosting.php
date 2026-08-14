<?php
return ['provisioning_mode'=>env('HOSTING_PROVISIONING_MODE','mock'),'allow_live_provisioning'=>(bool)env('ALLOW_HOSTING_PROVISIONING',false)];
