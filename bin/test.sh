#!/bin/bash
OIFS="$IFS"
IFS="
"

function test_hyperv() {
  id="$1"
  status=".status_$id"
  temp=".temp_$id"
  touch "$status"
  echo "Getting VM List" > "$status"
  ./hyperv_GetVMList.php "$id" > "$temp"
  count="$(grep Name  "$temp"|wc -l)"
  #templates="$(php ../get_hyperv_templates.php)"
  templates="Windows2016Standard"
  if [ $count -ge 2 ]; then
	echo "Found $count VMs, Creating VM" > "$status"
	sleep 3s;
	for template in $templates; do
	  if [ ! -e ".error_${id}" ]; then
		echo "Creating $template" > "$status"
		./hyperv_CreateVM.php "$id" "detain-qa-$id.is.cc" "45" "1024" "$template" > "${temp}_create"
		if [ "$(grep "Success.*=> 1" "${temp}_create")" != "" ]; then
		  uuid="$(grep "Status.*=> " "${temp}_create" | awk '{ print $3 }')"
		  echo "Got VM ID $uuid, Deleting VM" > "$status"
		  sleep 3s;
		  ./hyperv_DeleteVM.php "$id" "$uuid" > "${temp}_delete"
		  if [ "$(grep "Success.*=> 1" "${temp}_delete")" != "" ]; then
			echo "All Done, All Good" > "$status"
		  else
			echo "DeleteVM Error $(cat "${temp}_delete" |tr "\n" " ")" > ".error_${id}"
		  fi
		else
		  echo "CreateVM Error $(cat "${temp}_create" |tr "\n" " ")" > ".error_${id}"
		fi
		echo "Done, List/Create/Delete $template" > "$status"
                sleep 3s;
	  fi
	done
  elif [ "$(cat "$temp" |wc -l)$(cat "$temp" | grep Success |sed s#"^ *\[Success\] => *$"#"2"#g)" = "42" ]; then
	echo "Blank VMList Response" > ".error_${id}"
	sleep 3s;
  else
	echo -e "Error in Output" > ".error_${id}"
	cat "$temp" >> ".error_${id}"
	sleep 3s;
  fi
  if [ ! -e ".error_${id}" ]; then
	cat "$status" > ".ok_${id}"
  fi
  rm -f "$status" "$temp"
}

function show_progress() {
  clear
  count="$(cat .servers|wc -l)"
  echo
  printf -- "-=[ MyAdmin HyperV Tester ]=[ Status - %.3s Hosts ]=-\n" "$count"
  printf "%15s+%35s\n" Name--- Status--- | tr " " -
  for i in $(cat .servers); do
	id="$(echo "${i}"|cut -d, -f1)"
	name="$(echo "${i}"|cut -d, -f2)"
	ip="$(echo "${i}"|cut -d, -f3)"
	pass="$(echo "${i}"|cut -d, -f4-)"
	if [ -e ".error_${id}" ]; then
		  state="Error! $(cat .error_${id})"
	elif [ -e ".ok_${id}" ]; then
	  state="OK! $(cat .ok_${id})"
	else
	  state="$(cat ".status_${id}")"
	fi
	printf "%15s|%35s\n" "$name " "$state  "
  done
  echo
  read -s -n "1" -N "1" -t "$delay" -p "$(printf "%.20s %42s\n" "[Q]uit" "[S]low <<< ${delay}s >>> [F]ast")" prompt
  if [ "$prompt" = "q" ]; then
	ps aux|grep "php ./hyperv_"|grep -v "grep php" | awk '{ print $2 }' |xargs -n "1" kill -9
	ps uax|grep hyperv_|grep -v hyperv_test| awk '{ print $2 }' |xargs -n "1" kill -9
	ls .error_* .ok_* .status_* .temp_* 2>/dev/null | xargs -r rm -f
  elif [ "$prompt" = "f" ]; then
	if [ "$delay" -ge 2 ]; then
	  export delay="$(($delay - 1))"
	fi
  elif [ "$prompt" = "s" ]; then
	export delay="$(($delay + 1))"
  fi
}

export delay="5"
chmod a-rx /home/my/scripts/vps/vps_cron.sh;
php ../get_hyperv_servers.php > .servers
ls .error_* .ok_* .status_* .temp_* 2>/dev/null | xargs -r rm -f
for i in $(cat .servers); do
  id="$(echo "${i}"|cut -d, -f1)"
  name="$(echo "${i}"|cut -d, -f2)"
  ip="$(echo "${i}"|cut -d, -f3)"
  pass="$(echo "${i}"|cut -d, -f4-)"
  test_hyperv "$id" &
done
sleep 1s;
while [ "$(ls .status_* 2>/dev/null)" != "" ]; do
  show_progress
done
chmod a+rx /home/my/scripts/vps/vps_cron.sh;

