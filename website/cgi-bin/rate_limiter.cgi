#!/bin/bash
# Rate limiting script
LIMIT=5
IP=$(echo $REMOTE_ADDR)
TIME_FRAME=60

COUNT=$(grep -c $IP /tmp/rate_limit_log)

if [ "$COUNT" -ge "$LIMIT" ]; then
  echo "Content-type: text/html"
  echo ""
  echo "<html><body><h1>Rate limit exceeded. Try again later.</h1></body></html>"
  exit
fi

# Log the request
echo $IP >> /tmp/rate_limit_log

# Allow access to the actual presale page
echo "Content-type: text/html"
echo ""
cat ../index.html
