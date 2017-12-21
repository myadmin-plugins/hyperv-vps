#!/bin/bash
for s in 423" "440" "445" "448" "449" "450" "451" "453" "454" "455" "456" "457" "458; do
	echo "Getting HyperV Server $s List";
	for i in $(./hyperv_GetVMList.php "$s" |grep trouble-free.net | cut -d\. -f1 | awk '{ print $3 }' | sed s#"vps"#""#g); do
		echo "setting it on HyperV Server $s VPS $i";
		./hyperv_SetVMIOPS.php "$i"
		echo;
	done &
done
