<?php
require_once __DIR__ . "/func.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  $input = json_decode(file_get_contents('php://input'), true);
  // var_dump($input);
  setConfig(getUserId(), $input['config']);
  echo json_encode(['done' => true]);
  exit;
}

$config = getConfig(getUserId());
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Proxy Manager</title>
  <meta http-equiv="Content-type" content="text/html;charset=UTF-8">
  <link href="//cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
  <style>
    .embed-container {
      position: relative;
      padding-bottom: 56.25%;
      height: 0;
      overflow: hidden;
      max-width: 100%;
    }

    .embed-container iframe,
    .embed-container object,
    .embed-container embed {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
    }

    #snackbar {
      visibility: hidden;
      min-width: 250px;
      background-color: #333;
      color: #fff;
      text-align: center;
      border-radius: 2px;
      padding: 16px;
      position: fixed;
      z-index: 1;
      left: 50%;
      bottom: 30px;
      transform: translateX(-50%);
    }

    #snackbar.show {
      visibility: visible;
    }
  </style>
  <link rel="stylesheet" href="//rawcdn.githack.com/dimaslanjaka/Web-Manajemen/0f634f242ff259087c9fe176e8f28ccaebb5c015/css/all.min.css" />
</head>

<body>

  <main class="m-2">
    <div class="form-group mb-2">
      <div class="form-floating">
        <textarea class="form-control" placeholder="Add proxies here" style="height: 250px;" id="proxiesData" style="height: 100px"></textarea>
        <label for="proxiesData">Add Proxies</label>
      </div>
    </div>

    <div class="mb-4 btn-group text-white">
      <button class="btn btn-warning" id="addProxy"><i class="fa-duotone fa-plus mr-2"></i> Add Proxies</button>
      <button class="btn btn-info" id="checkProxy"><i class="fa-duotone fa-radar mr-2"></i> Check Proxies</button>
      <button class="btn btn-primary" id="refresh"><i class="fa-duotone fa-arrows-rotate mr-2"></i> Refresh</button>
    </div>

    <div class="accordion accordion-flush mb-4 border" id="accordionFlushExample">
      <div class="accordion-item">
        <h2 class="accordion-header border" id="flush-headingOne">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
            Advanced Options
          </button>
        </h2>
        <div id="flush-collapseOne" class="accordion-collapse collapse" aria-labelledby="flush-headingOne" data-bs-parent="#accordionFlushExample">
          <div class="accordion-body">
            <span class="mb-2">Your ID: <b id="uid"><?php echo $user_id ?></b></h2>
              <div class="form-group mb-2">
                <label for="endpoint">URL target to test</label>
                <input type="text" class="form-control" id="endpoint" placeholder="URL target to test" value="<?php echo $config['endpoint'] ?>" />
              </div>

              <div class="form-floating mb-2">
                <textarea class="form-control" style="height: 250px;" id="headers" style="height: 100px"><?php echo implode("\n", $config['headers']); ?></textarea>
                <label for="proxiesData">Custom Headers</label>
              </div>

              <button class="btn btn-primary" id="saveConfig"><i class="fa-duotone fa-save mr-2"></i> Save</button>
          </div>
        </div>
      </div>
    </div>

    <div class="mb-2 row">
      <div class="col-md-4 mb-2">
        Proxies list
        <div class='embed-container mt-2'><iframe src='./proxies.txt' frameborder='0' allowfullscreen></iframe></div>
      </div>
      <div class="col-md-4 mb-2">
        Working proxies list
        <div class='embed-container mt-2'><iframe src='./working.txt' frameborder='0' allowfullscreen></iframe></div>
      </div>
      <div class="col-md-4 mb-2">
        Checker result
        <div class='embed-container mt-2'><iframe src='./proxyChecker.txt' frameborder='0' allowfullscreen></iframe></div>
      </div>
    </div>

    <div class="mb-2">
      Dead proxies
      <blockquote>These proxies will be respawned when proxies list empty</blockquote>
      <div class='embed-container mt-2'><iframe src='./dead.txt' frameborder='0' allowfullscreen></iframe></div>
    </div>
  </main>

  <div id="snackbar">Snackbar Message</div>

  <script src="//cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
  <!-- <script src="//rawcdn.githack.com/dimaslanjaka/jquery-form-saver/38176c68300c834d6692953a1be7407caed01832/dist/release/autosave.js"></script> -->
  <script src="./proxyManager.js"></script>
</body>

</html>