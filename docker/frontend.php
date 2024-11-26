<!DOCTYPE html>
<html>
<head>
<link rel="shortcut icon" href="favicon.ico">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=no" />
<link rel="apple-touch-icon" href="favicon.ico">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="description" content="Take a speed test from your device to TorBox's servers to test your bandwidth speeds.">
<meta charset="UTF-8" />
<link rel="stylesheet" href="index.css">
<script type="text/javascript" src="speedtest.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
<script type="text/javascript">
function I(i){return document.getElementById(i);}

//LIST OF TEST SERVERS. See documentation for details if needed
var SPEEDTEST_SERVERS= <?= file_get_contents('/servers.json') ?: '[]' ?>;

//INITIALIZE SPEED TEST
var s=new Speedtest(); //create speed test object
<?php if(getenv("TELEMETRY")=="true"){ ?>
s.setParameter("telemetry_level","basic");
<?php } ?>
<?php if(getenv("DISABLE_IPINFO")=="true"){ ?>
s.setParameter("getIp_ispInfo",false);
<?php } ?>
<?php if(getenv("DISTANCE")){ ?>
s.setParameter("getIp_ispInfo_distance","<?=getenv("DISTANCE") ?>");
<?php } ?>

//SERVER AUTO SELECTION
function initServers(){
    var noServersAvailable=function(){
        I("message").innerHTML="No test servers available. Please try again later.";
    }
    var runServerSelect=function(){
        s.selectServer(function(server){
            if(server!=null){ //at least 1 server is available
                I("loading").className="hidden"; //hide loading message
                //populate server list for manual selection
                for(var i=0;i<SPEEDTEST_SERVERS.length;i++){
                    if(SPEEDTEST_SERVERS[i].pingT==-1) continue;
                    var option=document.createElement("option");
                    option.value=i;
                    option.textContent=SPEEDTEST_SERVERS[i].name;
                    if(SPEEDTEST_SERVERS[i]===server) option.selected=true;
                    I("server").appendChild(option);
                }
                //show test UI
                I("testWrapper").className="visible";
                initUI();
            }else{ //no servers are available, the test cannot proceed
                noServersAvailable();
            }
        });
    }
    if(typeof SPEEDTEST_SERVERS === "string"){
        //need to fetch list of servers from specified URL
        s.loadServerList(SPEEDTEST_SERVERS,function(servers){
            if(servers==null){ //failed to load server list
                noServersAvailable();
            }else{ //server list loaded
                SPEEDTEST_SERVERS=servers;
                runServerSelect();
            }
        });
    }else{
        //hardcoded server list
        s.addTestPoints(SPEEDTEST_SERVERS);
        runServerSelect();
    }
}

var meterBk=/Trident.*rv:(\d+\.\d+)/i.test(navigator.userAgent)?"#EAEAEA":"#80808040";
var dlColor="#04BF8A",
	ulColor="#F59E0B";
var progColor=meterBk;

//CODE FOR GAUGES
function drawMeter(c, amount, bk, fg, progress, prog) {
    var ctx = c.getContext("2d");
    var dp = window.devicePixelRatio || 1;
    var cw = c.clientWidth * dp, ch = c.clientHeight * dp;
    var sizScale = ch * 0.0055;
    if (c.width == cw && c.height == ch) {
        ctx.clearRect(0, 0, cw, ch);
    } else {
        c.width = cw;
        c.height = ch;
    }
    
    // Set lineCap to 'round' for rounded edges
    ctx.lineCap = "round"; 
    
    // Draw the background arc
    ctx.beginPath();
    ctx.strokeStyle = bk;
    ctx.lineWidth = 12 * sizScale;
    ctx.arc(c.width / 2, c.height - 58 * sizScale, c.height / 1.8 - ctx.lineWidth, -Math.PI * 1.1, Math.PI * 0.1);
    ctx.stroke();
    
    // Draw the foreground arc (progress)
    ctx.beginPath();
    ctx.strokeStyle = fg;
    ctx.lineWidth = 12 * sizScale;
    ctx.arc(c.width / 2, c.height - 58 * sizScale, c.height / 1.8 - ctx.lineWidth, -Math.PI * 1.1, amount * Math.PI * 1.2 - Math.PI * 1.1);
    ctx.stroke();
    
    // Draw the progress bar if progress is defined
    if (typeof progress !== "undefined") {
        ctx.fillStyle = prog;
        ctx.fillRect(c.width * 0.3, c.height - 16 * sizScale, c.width * 0.4 * progress, 4 * sizScale);
    }
}

function mbpsToAmount(s){
	return 1-(1/(Math.pow(1.3,Math.sqrt(s))));
}
function format(d){
    d=Number(d);
    if(d<10) return d.toFixed(2);
    if(d<100) return d.toFixed(1);
    return d.toFixed(0);
}

//UI CODE
var uiData=null;
function startStop(){
    if(s.getState()==3){
		//speed test is running, abort
		s.abort();
		data=null;
		I("startStopBtn").className="";
		I("server").disabled=false;
		initUI();
	}else{
		//test is not running, begin
		I("startStopBtn").className="running";
		I("shareArea").style.display="none";
		I("server").disabled=true;
		s.onupdate=function(data){
            uiData=data;
		};
		s.onend=function(aborted){
            I("startStopBtn").className="";
            I("server").disabled=false;
            updateUI(true);
            if(!aborted){
                //if testId is present, show sharing panel, otherwise do nothing
                try{
                    var testId=uiData.testId;
                    if(testId!=null){
                        var shareURL=window.location.href.substring(0,window.location.href.lastIndexOf("/"))+"/results/?id="+testId;
                        I("resultsImg").src=shareURL;
                        I("resultsURL").value=shareURL;
                        I("testId").innerHTML=testId;
                        I("shareArea").style.display="";
                    }
                }catch(e){}
            }
		};
		s.start();
	}
}
//this function reads the data sent back by the test and updates the UI
function updateUI(forced){
	if(!forced&&s.getState()!=3) return;
	if(uiData==null) return;
	var status=uiData.testState;
	I("ip").textContent=uiData.clientIp;
	I("dlText").textContent=(status==1&&uiData.dlStatus==0)?"...":format(uiData.dlStatus);
	drawMeter(I("dlMeter"),mbpsToAmount(Number(uiData.dlStatus*(status==1?oscillate():1))),meterBk,dlColor,Number(uiData.dlProgress),progColor);
	I("ulText").textContent=(status==3&&uiData.ulStatus==0)?"...":format(uiData.ulStatus);
	drawMeter(I("ulMeter"),mbpsToAmount(Number(uiData.ulStatus*(status==3?oscillate():1))),meterBk,ulColor,Number(uiData.ulProgress),progColor);
	I("pingText").textContent=format(uiData.pingStatus);
	I("jitText").textContent=format(uiData.jitterStatus);
}
function oscillate(){
	return 1+0.02*Math.sin(Date.now()/100);
}
//update the UI every frame
window.requestAnimationFrame=window.requestAnimationFrame||window.webkitRequestAnimationFrame||window.mozRequestAnimationFrame||window.msRequestAnimationFrame||(function(callback,element){setTimeout(callback,1000/60);});
function frame(){
	requestAnimationFrame(frame);
	updateUI();
}
frame(); //start frame loop
//function to (re)initialize UI
function initUI(){
	drawMeter(I("dlMeter"),0,meterBk,dlColor,0);
	drawMeter(I("ulMeter"),0,meterBk,ulColor,0);
	I("dlText").textContent="";
	I("ulText").textContent="";
	I("pingText").textContent="";
	I("jitText").textContent="";
	I("ip").textContent="";
}
</script>
<style type="text/css">
	html,body{
		border:none; padding:0; margin:0;
		background:#12141b;
		color:#FFFFFF;
	}
	body{
		text-align:center;
		font-family: "Inter", sans-serif;
	}
	#loading{
		/* background-color:#12141b; */
		color:#FFFFFF;
		text-align:center;
	}
	span.loadCircle{
		display:inline-block;
		width:2em;
		height:2em;
		vertical-align:middle;
		background:url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAMAAAD04JH5AAAAP1BMVEUAAAB2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZyFzwnAAAAFHRSTlMAEvRFvX406baecwbf0casimhSHyiwmqgAAADpSURBVHja7dbJbQMxAENRahnN5lkc//5rDRAkDeRgHszXgACJoKiIiIiIiIiIiIiIiIiIiIj4HHspsrpAVhdVVguzrA4OWc10WcEqpwKbnBo0OU1Q5NSpsoJFTgOecrrdEag85DRgktNqfoEdTjnd7hrEHMEJvmRUYJbTYk5Agy6nau6Abp5Cm7mDBtRdPi9gyKdU7w4p1fsLvyqs8hl4z9/w3n/Hmr9WoQ65lAU4d7lMYOz//QboRR5jBZibLMZdAR6O/Vfa1PlxNr3XdS3HzK/HVPRu/KnLs8iAOh993VpRRERERMT/fAN60wwWaVyWwAAAAABJRU5ErkJggg==');
		background-size:2em 2em;
		margin-right:0.5em;
		animation: spin 0.6s linear infinite;
	}
	@keyframes spin{
		0%{transform:rotate(0deg);}
		100%{transform:rotate(359deg);}
	}
	#startStopBtn{
		display:inline-block;
		margin:0 auto;
		color:#FFFFFF;
		background-color:rgba(0,0,0,0);
		border:0.15em solid #FFFFFF;
		border-radius:0.3em;
		transition:all 0.3s;
		box-sizing:border-box;
		width:8em; height:3em;
		line-height:2.7em;
		cursor:pointer;
		box-shadow: 0 0 0 rgba(0,0,0,0.1), inset 0 0 0 rgba(0,0,0,0.1);
	}
	#startStopBtn:hover{
		box-shadow: 0 0 2em rgba(0,0,0,0.1), inset 0 0 1em rgba(0,0,0,0.1);
	}
	#startStopBtn.running{
		background-color:#FF3030;
		border-color:#FF6060;
		color:#FFFFFF;
	}
	#startStopBtn:before{
		content:"Start";
	}
	#startStopBtn.running:before{
		content:"Abort";
	}
	#serverArea{
		margin-top:1em;
	}
	#server{
		font-size:1em;
		padding:0.2em;
	}
	#test{
		margin-top:2em;
	}
	div.testArea{
		display:inline-block;
		width:16em;
		height:12.5em;
		position:relative;
		box-sizing:border-box;
	}
	div.testArea2{
		display:inline-block;
		width:16em;
		height:8em;
		position:relative;
		box-sizing:border-box;
		text-align:center;
	}
	div.testArea div.testName{
		position:absolute;
		top:-0.8em; left:0;
		width:100%;
		z-index:9;
	}
	div.testArea2 div.testName{
        display:block;
        text-align:center;
	}
	div.testArea div.meterText{
		position:absolute;
		bottom:1.55em; left:0;
		width:100%;
		font-size:2.5em;
		z-index:9;
	}
	div.testArea2 div.meterText{
        display:inline-block;
        font-size:2.5em;
	}
	div.meterText:empty:before{
		content:"0.00";
	}
	div.testArea div.unit{
		position:absolute;
		bottom:2em; left:0;
		width:100%;
		z-index:9;
	}
	div.testArea2 div.unit{
		display:inline-block;
	}
	div.testArea canvas{
		position:absolute;
		top:0; left:0; width:100%; height:100%;
		z-index:1;
	}
	div.testGroup{
		display:block;
        margin: 0 auto;
	}
	#shareArea{
		width:95%;
		max-width:40em;
		margin:0 auto;
		margin-top:2em;
	}
	#shareArea > *{
		display:block;
		width:100%;
		height:auto;
		margin: 0.25em 0;
	}
	#privacyPolicy{
        position:fixed;
        top:2em;
        bottom:2em;
        left:2em;
        right:2em;
        overflow-y:auto;
        width:auto;
        height:auto;
        box-shadow:0 0 3em 1em #000000;
        z-index:999999;
        text-align:left;
        background-color:#FFFFFF;
        padding:1em;
	}
	a.privacy{
        text-align:center;
        font-size:0.8em;
        color:#808080;
        display:block;
	}
	@media all and (max-width:40em){
		body{
			font-size:0.8em;
		}
	}
	div.visible{
		animation: fadeIn 0.4s;
		display:block;
	}
	div.hidden{
		animation: fadeOut 0.4s;
		display:none;
	}
	@keyframes fadeIn{
		0%{
			opacity:0;
		}
		100%{
			opacity:1;
		}
	}
	@keyframes fadeOut{
		0%{
			display:block;
			opacity:1;
		}
		100%{
			display:block;
			opacity:0;
		}
	}
</style>
<title>TorBox Speed Test</title>
</head>
<body onload="initServers()">
<header class="w-full px-4 header">
    <div class="flex items-center justify-between w-full py-4 mx-auto max-w-7xl">
        <div class="flex items-center">
          <div class="w-20 h-20 -ml-4">
            <svg
              version="1.1"
              id="Layer_1"
              xmlns="http://www.w3.org/2000/svg"
              xmlnsXlink="http://www.w3.org/1999/xlink"
              x="0px"
              y="0px"
              viewBox="0 0 1500 1500"
              xmlSpace="preserve"
            >
              <g>
                <polygon
                  style="fill:#00444D;"
                  points="749.99,749.99 749.99,1191.96 367.25,970.97 367.25,529.01"
                />
                <polygon
                  style="fill:#34BA90;"
                  points="1132.75,529.01 1132.75,970.97 749.99,1191.96 749.99,749.99 872.87,679.05 956.71,630.66"
                />
                <polygon
                  style="fill:#52A153;"
                  points="1132.75,529.01 749.99,749.99 367.25,529.01 749.99,308.04"
                />
                <polygon
                  style="fill:#FFFFFF"
                  points="1043.04,739.36 958.66,1057.08 952.4,851.84 839.71,915.39 872.87,679.05 956.71,630.66 931.81,799.21"
                />
              </g>
            </svg>
          </div>
          <h1 class="-ml-3 text-3xl font-medium uppercase font-['TorBox_Dotted']">Speed Test</h1>
        </div>
      <div class="flex items-center justify-center gap-6">
        <a
          href="https://torbox.app/pricing"
          class="hidden text-sm text-gray-300 sm:block"
          aria-label="Pricing"
          title="Pricing"
          name="Pricing"
        >
          Pricing
        </a>
        <a
          href="https://support.torbox.app"
          target="_blank"
          class="hidden text-sm text-gray-300 sm:block"
          aria-label="Help Center"
          title="Help Center"
          name="Help Center"
        >
          Help Center
        </a>
        <a
          href="https://api-docs.torbox.app"
          target="_blank"
          class="hidden text-sm text-gray-300 sm:block"
          aria-label="API"
          title="API"
          name="API"
        >
          API
        </a>
        <a
          href="https://join-discord.torbox.app"
          target="_blank"
          class="hidden text-sm text-gray-300 sm:block"
          aria-label="Discord"
          title="Discord"
          name="Discord"
        >
          Discord
        </a>
        <a
          href="https://status.torbox.app"
          target="_blank"
          class="hidden text-sm text-gray-300 sm:block"
          aria-label="Status"
          title="Status"
          name="Status"
        >
          Status
        </a>
        <a
          href="https://torbox.app/about"
          class="hidden text-sm text-gray-300 sm:block"
          aria-label="about"
          title="about"
          name="about"
        >
          About
        </ak>
        <div class="rounded-md bg-gradient-to-r from-[#026873] via-[#04BF8A] to-[#025940] p-[1px]">
          <a
            href="https://torbox.app/dashboard"
            class="flex items-center justify-center bg-[#1c1c1c] rounded-md p-2"
            aria-label="Login"
            title="Login"
            name="Login"
          >
            <h1 class="px-3 font-medium text-white text-lg uppercase font-['TorBox_Dotted']">
              Dashboard
            </h1>
          </a>
        </div>
      </div>
    </div>
  </header>
  <div class="w-full px-4">
  	<div class="w-full border p-4 rounded-md border-[#212A36] bg-[#1E2129] max-w-7xl mb-4 mx-auto flex flex-wrap justify-center items-center gap-2 min-h-[500px] h-full">
		<div id="loading" class="visible h-full flex flex-col justify-center items-center">
			<p id="message"><span class="loadCircle"></span>Selecting a server...</p>
		</div>
		<div id="testWrapper" class="hidden mt-10">
			<div id="startStopBtn" onclick="startStop()"></div>
			<div id="serverArea">
				Server: <select id="server" onchange="s.setSelectedServer(SPEEDTEST_SERVERS[this.value])" class="bg-[#12141b] border rounded-md border-[#302f2f] hover:border-[#04BF8A] transition duration-300 ease-in-out"></select>
			</div>
			<div id="test">
				<div class="testGroup">
					<div class="testArea2">
						<div class="testName mb-1 text-4xl font-medium uppercase font-['TorBox_Dotted']">Ping</div>
						<div id="pingText" class="meterText" style="color:#04BF8A"></div>
						<div class="unit">ms</div>
					</div>
					<div class="testArea2">
						<div class="testName mb-1 text-4xl font-medium uppercase font-['TorBox_Dotted']">Jitter</div>
						<div id="jitText" class="meterText" style="color:#04BF8A"></div>
						<div class="unit">ms</div>
					</div>
				</div>
				<div class="testGroup mt-4">
					<div class="testArea">
						<div class="testName mb-4 text-4xl font-medium uppercase font-['TorBox_Dotted'] mt-4">Download</div>
						<canvas id="dlMeter" class="meter"></canvas>
						<div id="dlText" class="meterText"></div>
						<div class="unit">Mbps</div>
					</div>
					<div class="testArea">
						<div class="testName mb-4 text-4xl font-medium uppercase font-['TorBox_Dotted'] mt-4">Upload</div>
						<canvas id="ulMeter" class="meter"></canvas>
						<div id="ulText" class="meterText"></div>
						<div class="unit">Mbps</div>
					</div>
				</div>
				<div id="ipArea" style="display:none">
					<span id="ip"></span>
				</div>
				<div id="shareArea" style="display:none">
					<h3>Share results</h3>
					<p>Test ID: <span id="testId"></span></p>
					<input type="text" value="" id="resultsURL" readonly="readonly" onclick="this.select();this.focus();this.select();document.execCommand('copy');alert('Link copied')"/>
					<img src="" id="resultsImg" />
				</div>
			</div>
		</div>
	</div>
	<div class="w-full">
  		<div class="w-full border p-4 rounded-md border-[#212A36] bg-[#1E2129] max-w-7xl mb-4 mx-auto flex flex-wrap justify-center items-center gap-2 h-full">
			<div>
				<h1 class="text-3xl font-medium uppercase font-['TorBox_Dotted']">Test Files</h1>
				<p class="text-gray-300">Download test files to check your end-to-end download speeds.</p>
			</div>
			<div class="w-full flex flex-wrap justify-center items-center gap-10">
				<div class="flex flex-col">
					<p class="font-bold">EEUR Test Files</p>
					<p class="text-gray-300">Hosted in the Netherlands</p>
					<a href="https://storage.torbox.app/100MB.bin" class="rounded-md p-2 text-white font-semibold underline">100MB Test File</a>
					<div class="w-1 border hidden sm:block"></div>
					<a href="https://storage.torbox.app/1GB.bin" class="rounded-md p-2 text-white font-semibold underline">1GB Test File</a>
					<div class="w-1 border hidden sm:block"></div>
					<a href="https://storage.torbox.app/10GB.bin" class="rounded-md p-2 text-white font-semibold underline">10GB Test File</a>
				</div>
				<div class="flex flex-col">
				<p class="font-bold">WNAM CDN Test Files</p>
					<p class="text-gray-300">Hosted on the USA CDN</p>
					<a href="https://hyperdrive.test.wnam.torbox.app/100MB.bin" class="rounded-md p-2 text-white font-semibold underline">100MB Test File</a>
					<div class="w-1 border hidden sm:block"></div>
					<a href="https://hyperdrive.test.wnam.torbox.app/1GB.bin" class="rounded-md p-2 text-white font-semibold underline">1GB Test File</a>
					<div class="w-1 border hidden sm:block"></div>
					<a href="https://hyperdrive.test.wnam.torbox.app/10GB.bin" class="rounded-md p-2 text-white font-semibold underline">10GB Test File</a>
				</div>
				<div class="flex flex-col">
					<p class="font-bold">APAC CDN Test Files</p>
					<p class="text-gray-300">Hosted on the Singapore CDN</p>
					<a href="https://hyperdrive.test.apac.torbox.app/100MB.bin" class="rounded-md p-2 text-white font-semibold underline">100MB Test File</a>
					<div class="w-1 border hidden sm:block"></div>
					<a href="https://hyperdrive.test.apac.torbox.app/1GB.bin" class="rounded-md p-2 text-white font-semibold underline">1GB Test File</a>
					<div class="w-1 border hidden sm:block"></div>
					<a href="https://hyperdrive.test.apac.torbox.app/10GB.bin" class="rounded-md p-2 text-white font-semibold underline">10GB Test File</a>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
