<?php

function getRandomItemFromArray($array)
{
    // Get a random key from the array
    $randomKey = array_rand($array);

    // Use the random key to get the random item
    $randomItem = $array[$randomKey];

    // Return the random item
    return $randomItem;
}

function webgl_data()
{
    return array(
        "Google Inc." => array(
            "Intel Inc." => array(
                "ANGLE (Intel(R) HD Graphics 530 Direct3D11 vs_5_0 ps_5_0)",
                "Intel Iris OpenGL Engine",
                "Intel Iris Pro OpenGL Engine",
                "Intel HD Graphics 4000 OpenGL Engine",
                "ANGLE (Intel, Intel(R) HD Graphics 400 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (Intel(R) HD Graphics 520 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (Intel(R) HD Graphics 5300 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (Intel(R) HD Graphics 620 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (Intel(R) HD Graphics 620 Direct3D9Ex vs_3_0 ps_3_0)",
                "ANGLE (Intel(R) HD Graphics Direct3D11 vs_4_1 ps_4_1)",
                "ANGLE (Intel(R) UHD Graphics 620 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (Intel(R) HD Graphics 4400 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (Intel(R) HD Graphics Family Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (Intel(R) HD Graphics 610 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (Intel(R) UHD Graphics Direct3D11 vs_5_0 ps_5_0, D3D11-27.20.100.8935)",
                "ANGLE (Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0, D3D11-27.20.100.8681)",
                "ANGLE (Intel(R) HD Graphics 5500 Direct3D11 vs_5_0 ps_5_0, D3D11-20.19.15.5126)",
                "ANGLE (Intel(R) HD Graphics 6000 Direct3D11 vs_5_0 ps_5_0, D3D11-20.19.15.5126)",
                "ANGLE (Intel(R) HD Graphics 620 Direct3D11 vs_5_0 ps_5_0, D3D11-27.20.100.8681)",
                "ANGLE (Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0, D3D11-27.20.100.9168)",
                "ANGLE (Intel(R) HD Graphics Direct3D11 vs_5_0 ps_5_0, D3D11-27.21.14.6589)",
                "ANGLE (Intel(R) UHD Graphics 620 Direct3D11 vs_5_0 ps_5_0, D3D11-27.20.100.9126)",
                "ANGLE (Intel, Mesa Intel(R) UHD Graphics 620 (KBL GT2), OpenGL 4.6 (Core Profile) Mesa 21.2.2)"
            ),
            "NVIDIA Corporation" => array(
                "ANGLE (NVIDIA, NVIDIA GeForce GTX 1650 Direct3D11 vs_5_0 ps_5_0, D3D11)",
                "ANGLE (NVIDIA, NVIDIA GeForce GTX 660 Direct3D11 vs_5_0 ps_5_0, D3D11)",
                "ANGLE (NVIDIA, NVIDIA GeForce GTX 1050 Ti Direct3D11 vs_5_0 ps_5_0, D3D11-25.21.14.1917)",
                "ANGLE (NVIDIA GeForce GTX 1050 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce GTX 1660 Ti Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce RTX 2070 SUPER Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce GTX 750 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA Quadro K600 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA Quadro M1000M Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce GTX 760 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce GTX 750 Ti Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce GTX 770 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce GTX 780 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce GTX 850M Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce GTX 860M Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce GTX 950 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce GTX 950M Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce GTX 960 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce GTX 960M Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce GTX 970 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce GTX 980 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce GTX 980 Ti Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce GTX 980M Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce MX130 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce MX150 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce MX230 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce MX250 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce RTX 2060 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce RTX 2060 SUPER Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA GeForce RTX 2070 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA Quadro K620 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA Quadro FX 380 Direct3D11 vs_4_0 ps_4_0)",
                "ANGLE (NVIDIA Quadro NVS 295 Direct3D11 vs_4_0 ps_4_0)",
                "ANGLE (NVIDIA Quadro P1000 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA Quadro P2000 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA Quadro P400 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA Quadro P4000 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA Quadro P600 Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (NVIDIA Quadro P620 Direct3D11 vs_5_0 ps_5_0)"
            ),
            "ATI Technologies Inc." => array(
                "ATI Radeon HD 5870",
                "ATI FirePro V8800",
                "AMD Radeon R9 290X",
                "ATI Radeon RX 570",
                "ATI Mobility Radeon HD 4330 Direct3D11 vs_4_1 ps_4_1",
                "ATI Mobility Radeon HD 4500 Series Direct3D11 vs_4_1 ps_4_1",
                "ATI Mobility Radeon HD 5000 Series Direct3D11 vs_5_0 ps_5_0",
                "ATI Mobility Radeon HD 5400 Series Direct3D11 vs_5_0 ps_5_0",
                "ANGLE (ATI Mobility Radeon HD 4330 Direct3D11 vs_4_1 ps_4_1)",
                "ANGLE (ATI Mobility Radeon HD 4500 Series Direct3D11 vs_4_1 ps_4_1)",
                "ANGLE (ATI Mobility Radeon HD 5000 Series Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (ATI Mobility Radeon HD 5400 Series Direct3D11 vs_5_0 ps_5_0)"
            ),
            "Advanced Micro Devices, Inc." => array(
                "AMD Radeon HD 7970",
                "AMD Radeon RX 5700 XT",
                "AMD Radeon Vega Frontier Edition",
                "ANGLE (AMD Radeon (TM) R9 370 Series Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (AMD Radeon HD 7700 Series Direct3D9Ex vs_3_0 ps_3_0)",
                "ANGLE (AMD Radeon(TM) Graphics Direct3D11 vs_5_0 ps_5_0)",
                "ANGLE (AMD, Radeon (TM) RX 470 Graphics Direct3D11 vs_5_0 ps_5_0, D3D11-27.20.1034.6)",
                "ANGLE (AMD, AMD Radeon(TM) Graphics Direct3D11 vs_5_0 ps_5_0, D3D11-27.20.14028.11002)",
                "ANGLE (AMD, AMD Radeon RX 5700 XT Direct3D11 vs_5_0 ps_5_0, D3D11-30.0.13025.1000)",
                "ANGLE (AMD, AMD Radeon RX 6900 XT Direct3D11 vs_5_0 ps_5_0, D3D11-30.0.13011.1004)",
                "ANGLE (AMD, AMD Radeon(TM) Graphics Direct3D11 vs_5_0 ps_5_0, D3D11-30.0.13002.23)"
            )
        )
    );
}

class WebGLData
{
    public $browser_vendor;
    public $webgl_vendor;
    public $webgl_renderer;

    public function __construct($browser_vendor, $webgl_vendor, $webgl_renderer)
    {
        $this->browser_vendor = $browser_vendor;
        $this->webgl_vendor = $webgl_vendor;
        $this->webgl_renderer = $webgl_renderer;
    }
}

function random_webgl_data()
{
    $data = webgl_data();
    $outerKey = getRandomItemFromArray(array_keys($data));
    $innerKey = getRandomItemFromArray(array_keys($data[$outerKey]));
    $renderers = getRandomItemFromArray(array_values($data[$outerKey][$innerKey]));
    // return ['browser_vendor' => $outerKey, 'webgl_vendor' => $innerKey, 'webgl_renderer' => $renderers];
    return new WebGLData($outerKey, $innerKey, $renderers);
}

// var_dump(random_webgl_data());
