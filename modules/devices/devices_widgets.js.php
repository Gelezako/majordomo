<?php
chdir(dirname(__FILE__) . '/../../');
include_once("./config.php");
include_once("./lib/loader.php");
$db = new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME);
include_once("./load_settings.php");
?>

var activeDevices = Array();
var devicesWidgetWSTimer;
var devicesWidgetWSUpdatedTimer;

$.subscribe('wsData', function (_, response) {
    if (response.action=='subscribed') {
        //console.log('Subscription to devices confirmed.');
    }
    if (response.action=='devices') {
        var obj=jQuery.parseJSON(response.data);
        if (typeof obj.DATA !='object') return false;
        var objCnt = obj.DATA.length;
        if (objCnt) {
            for(var i=0;i<objCnt;i++) {
                var device_id=obj.DATA[i].DEVICE_ID;
                var html=obj.DATA[i].DATA;
                $('#device'+device_id).html(html);
            }
        }
    }
});

function refreshWSSubscription() {
    clearTimeout(devicesWidgetWSTimer);
    //console.log('refresh subscription');
    if (startedWebSockets) {
        for(var i=0;i<activeDevices.length;i++) {
            //console.log('subscribing ws to device '+activeDevices[i]);
            var payload;
            payload = new Object();
            payload.action = 'Subscribe';
            payload.data = new Object();
            payload.data.TYPE='devices';
            payload.data.DEVICE_ID=activeDevices[i];
            wsSocket.send(JSON.stringify(payload));
        }
        devicesWidgetWSTimer=setTimeout('refreshWSSubscription();',60000);
    } else {
        devicesWidgetWSTimer=setTimeout('refreshWSSubscription();',5000);
    }
}

function activeDevicesUpdated() {
    clearTimeout(devicesWidgetWSUpdatedTimer);
    devicesWidgetWSUpdatedTimer=setTimeout('refreshWSSubscription();',2000);
}



function requestDeviceHTML(device_id,widgetElement) {
    //alert('requested html for '+device_id+' ');

    if (activeDevices.indexOf(device_id)<0) {
        activeDevices.push(device_id);
        activeDevicesUpdated();
    }

    var url='<?php echo ROOTHTML;?>ajax/devices.html?op=get_device&id='+device_id;
    $.ajax({
        url: url
    }).done(function(data) {
        var res=JSON.parse(data);
        if (typeof res.HTML !== 'undefined') {
            //alert(res.HTML);
            var myTextElement = $("<div id='device"+device_id+"'>"+res.HTML+"</div>");
            $(widgetElement).append(myTextElement);
            //subscribe to changes
        }
    });


}

(function()
{

    freeboard.loadWidgetPlugin({
        // Same stuff here as with datasource plugin.
        "type_name"   : "devices_plugin",
        "display_name": "Device",
        "description" : "MajorDoMo devices",
        "fill_size" : false,
        "settings"    : [
            {
                "name"        : "device_id",
                "display_name": "Device",
                "required" : true,
                "type"        : "option",
                <?php
                $scripts=SQLSelect("SELECT ID,TITLE FROM devices ORDER BY TITLE");
                ?>
                "options"     : [
                    <?php
                    foreach($scripts as $k=>$v) {
                        echo '{';
                        echo '"name" : "'.($v['TITLE']).'",'."\n";
                        echo '"value" : "'.$v['ID'].'"';
                        echo '},';
                    }
                    ?>
                ]
            }

        ],
// Same as with datasource plugin, but there is no updateCallback parameter in this case.
        newInstance   : function(settings, newInstanceCallback)
        {
            newInstanceCallback(new myDevicesPlugin(settings));
        }
    });

    var myDevicesPlugin = function(settings)
    {
        var self = this;
        var currentSettings = settings;
        var widgetElement;
        function updateDeviceHTML()
        {
            if(widgetElement)
            {
                requestDeviceHTML(currentSettings.device_id,widgetElement);
            }
        }

        self.render = function(element)
        {
            widgetElement = element;
            updateDeviceHTML();
        }

        self.getHeight = function()
        {
            return 1;
        }

        self.onSettingsChanged = function(newSettings)
        {
            currentSettings = newSettings;
            updateDeviceHTML();
        }

        self.onCalculatedValueChanged = function(settingName, newValue)
        {
            updateDeviceHTML();
        }

        self.onDispose = function()
        {
        }

    }


}());

<?php
$db->Disconnect();
?>