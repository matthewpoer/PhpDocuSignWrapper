#!/usr/bin/env bash

# this utility will solicit your DocuSign credentials and provide you with the
# base64'd and encoded (?) API Password, so you don't have to leave your
# cleartext password sitting in some code or config. file.

echo "Host? enter a subdomain, i.e. 'demo' or 'www'"
read Host

echo "Username:"
read Username

echo "Password:"
read -s Password

echo "Integrator Key:"
read IntegratorKey

echo "requesting API Password..."

OutputFile='ApiPasswordTmpFile.txt'
response=$(curl -i -s -o "$OutputFile" -X GET \
  "https://$Host.docusign.net/restapi/v2/login_information?api_password=true" \
  -H 'Content-Type: application/json' \
  -H "X-DocuSign-Authentication: {\"Username\":\"$Username\",\"Password\":\"$Password\",\"IntegratorKey\":\"$IntegratorKey\"}")
apiPassword=$(grep 'apiPassword' "$OutputFile")

if [[ $apiPassword = '' ]]; then
  echo "API Password could not be found. Outputting full DocuSign response:"
  echo "`cat "$OutputFile"`"
else
  echo "API Password Found:"
  echo $apiPassword;
fi
rm "$OutputFile"
