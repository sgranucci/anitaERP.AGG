<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title></title>
  </head>
  <body>
	  <div class="text-center" style="padding: 10px;">
	        <img src="data:image/png;base64, {{ base64_encode(QrCode::format('png')->size(300)->generate($url)) }} ">
	  </div>
  </body>
</html>

