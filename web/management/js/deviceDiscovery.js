var _serviceIP = "../stubs";

var cbs = [];
var foundDevices = [];
var preExistingDevices = [];
var numOfGetRequests = 0;
var numOfReturnedGetRequests = 0;

function ajaxRequest(){
	var request=false;
	try {
		request = new XMLHttpRequest()}catch(e1){
		try{
			request = new ActiveXObject("Msxml2.XMLHTTP")}catch(e2){
			try{
				request = new ActiveXObject("Microsoft.XMLHTTP")
			}
			catch(e3){request = false}
		}
	}
	return request
}

$('#startDiscoveryButton').click( function() {
	console.log("Discovery started");
	$('#statusLabel').show();
	$('#app').hide();

	cbs = [];
	foundDevices = [];
	preExistingDevices = [];
	numOfGetRequests = 0;
	numOfReturnedGetRequests = 0;

	$.ajax({
		url: "../api/deviceDiscoveryApi.php",
		data: {
			action: "getCBServiceTrees",
			token : sessionToken,
			organization: organization,
			username: loggedUser,
			loggedrole: loggedRole
		},
		type: "POST",
		async: true,
		datatype: 'json',
		success: function (data) {
			var content = data["content"];
			if(content.length>0) {
				//Building JSON hierarchy
				let ips = [];
				let ports = [];
				let accesslinks = [];
				let accessports = [];
				let paths = [];
				let logins = [];
				let passwords = [];

				for (row of content) {
					if (!cbs.includes(row.contextBroker)) {
						cbs.push(row.contextBroker);
						ips.push(row.ip);
						ports.push(row.port);
						accesslinks.push(row.accesslink);
						accessports.push(row.accessport);
						paths.push(row.path);
						logins.push(row.login);
						passwords.push(row.password);
					}
				}
				for (let i = 0; i < cbs.length; i++) {
					let cbName = cbs[i];
					cbs[i] = new Object({name: cbName, ip: ips[i], port: ports[i], accesslink: accesslinks[i], accessport: accessports[i],
						path: paths[i], login: logins[i], password: passwords[i]});
					let tenants = [];
					for (row of content) {
						if (row.contextBroker == cbName) {
							if (!tenants.includes(row.service))
								tenants.push(row.service!=null?row.service:"");
						}
					}
					cbs[i].tenants = tenants;
				}

				for (let i = 0; i < cbs.length; i++) {
					for (let j = 0; j < cbs[i].tenants.length; j++) {
						let tenantName = cbs[i].tenants[j];
						cbs[i].tenants[j] = new Object({name: tenantName});
						let servicePaths = [];
						for (let k = 0; k < content.length; k++) {
							let servicePath = content[k].servicePath;
							if (content[k].contextBroker == cbs[i].name && (content[k].service == tenantName ||
								(content[k].service == null && tenantName == ""))) {
								servicePaths.push(servicePath);
							}
						}
						cbs[i].tenants[j].servicePaths = servicePaths;
					}
				}

				// Now we have to check for implicit missing paths. What I mean is:
				// Let's assume that in a certain Tenant we have paths "path1" and "path1/path1_1/path1_1_1"
				// We want to scan for new devices also in "path1/path1_1"! The situation described above happens
				// if we don't have a Device, deleted device, or Device model, with the explicit path "path1/path1_1".

				for (let i = 0; i < cbs.length; i++) {
					for (let j = 0; j < cbs[i].tenants.length; j++) {
						for (let k = 0; k < cbs[i].tenants[j].servicePaths.length; k++) {
							let path = cbs[i].tenants[j].servicePaths[k];
							let slashes = (path.split("/")).length - 1;
							for (let w = 0; w < slashes; w++) {
								path = path.substring(0, path.lastIndexOf("/"));
								if (!cbs[i].tenants[j].servicePaths.includes(path)) {
									cbs[i].tenants[j].servicePaths.push(path);
									k = 0;
									console.log("Path inferred: " + path + " in CB: " + cbs[i].name + ", tenant: " + cbs[i].tenants[j].name);
								}
							}
						}
					}
				}

				//console.log("Completed JSON hierarchy:");
				//console.log(cbs);

				// Now we have to retrieve all devices in every path of every tenant of every contextBroker,
				// and this is done by calling activateStub on every path.
				for (let i = 0; i < cbs.length; i++) {
					for (let j = 0; j < cbs[i].tenants.length; j++) {
						for (let k = 0; k < cbs[i].tenants[j].servicePaths.length; k++) {
							numOfGetRequests++;
						}
					}
				}
				for (let i = 0; i < cbs.length; i++) {
					for (let j = 0; j < cbs[i].tenants.length; j++) {
						for (let k = 0; k < cbs[i].tenants[j].servicePaths.length; k++) {
							activateStub("discover",cbs[i].name,cbs[i].ip,cbs[i].port,cbs[i].accesslink,cbs[i].accessport,
								cbs[i].path, organization, cbs[i].login,cbs[i].password,cbs[i].tenants[j].name,cbs[i].tenants[j].servicePaths[k]);
						}
					}
				}

			}else{
				console.log("Warning - Empty response!");
			}
		},
		error: function (data)
		{
			console.log("ERROR in getCBServiceTrees: "+JSON.stringify(data));
		}
	});

});

function activateStub(protocol,cb,ip,port,accesslink,accessport,path,organization,login,password,tenant,servicePath, apikey)
{
	if(servicePath != null && servicePath != undefined && servicePath.localeCompare("null")!=0 && servicePath.localeCompare("")!=0){
		servicePath = "/"+servicePath;
	}

	//console.log("STUB: "+protocol+". CB: "+ cb+ ", IP: "+ip+ ", port: "+ port+ ", acc. link: " + accesslink+ ", acc. port: " + accessport+ ", path: "+path+", organization: "+organization+", tenant: "+tenant+ ", service path: " +servicePath);

	var data;
	if(apikey != null && apikey != undefined){
		data = "contextbroker=" + cb + "&ip=" + ip + "&port=" +port+ "&al="+accesslink + "&ap="+accessport+ "&path="+path+"&organization="+organization+"&login="+login+"&password="+password+"&tenant="+tenant+"&servicepath="+servicePath+"&apikey="+apikey;
	}
	else{
		data = "contextbroker=" + cb + "&ip=" + ip + "&port=" +port+ "&al="+accesslink + "&ap="+accessport+ "&path="+path+"&organization="+organization+"&login="+login+"&password="+password+"&tenant="+tenant+"&servicepath="+servicePath	}
	var service = _serviceIP + "/api/"+protocol;

	var xhr = ajaxRequest();

	xhr.addEventListener("readystatechange", function () {
		//console.log("this.readyState "+this.readyState);
		if (this.readyState === 4 && this.status == 200) {
			let jsonResponse = JSON.parse(this.responseText).message;
			//console.log(jsonResponse);
			if(jsonResponse=="not reacheable\n"){
				console.error("Context Broker "+cb+ " is not reachable.");
				$('#statusLabel').text("Context Broker "+cb+ " is not reachable.");
			}else if(jsonResponse=="path malformed\n"){
				console.error("Context Broker "+cb+ ": path malformed.");
				$('#statusLabel').text("Context Broker "+cb+ ": path malformed.");
			}else if(jsonResponse=="not found\n"){
				console.error("Context Broker "+cb+ " not found.");
				$('#statusLabel').text("Context Broker "+cb+ " not found.");
			}else{
				let devices = [];
				for(let i =0; i<jsonResponse.length;i++){
					let device = jsonResponse[i];
					device.contextBroker = cb;
					device.service = tenant;
					device.servicePath = servicePath;
					devices.push(device);
				}
				//console.log(devices);
				saveFoundDevices(devices);
			}
		}
	});

	xhr.open("POST", service);
	xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	xhr.setRequestHeader('Cache-Control', 'no-cache');
	xhr.setRequestHeader("Access-Control-Allow-Origin", "*");
	xhr.send(data);
}

function saveFoundDevices(devices){
	for(let i =0; i<devices.length;i++){
		foundDevices.push(devices[i]);
	}
	numOfReturnedGetRequests++;
	if(numOfReturnedGetRequests==numOfGetRequests){
		console.log("Got all devices from external CBs. There are "+foundDevices.length+" devices.");
		// now we can proceed in getting all pre-existing external devices on the IOT, so we can later compare the two sets
		getPreExistingExternalDevices();
	}
}

function getPreExistingExternalDevices(){
	console.log("Getting devices from IOT Directory");
	// call api get_all_ext_devices_in_iot_dir
	$.ajax({
		url: "../api/deviceDiscoveryApi.php",
		data: {
			action: "get_all_ext_devices_in_iot_dir",
			token: sessionToken,
			organization: organization,
			username: loggedUser,
			loggedrole: loggedRole
		},
		type: "POST",
		async: true,
		datatype: 'json',
		success: function (data) {
			var content = data["content"];
			// Build device list, put them in preExistingDevices
			for(let i = 0; i<content.length;i++){
				// In the DB the id of the device is in the form "tenant.path.deviceName
				// but I am only interested in the name
				content[i].id = content[i].id.replace(content[i].service,"").replace(content[i].servicePath,"").replace("..","");
				if(content[i].servicePath.length>0){
					content[i].servicePath = "/"+content[i].servicePath;
				}
			}
			//console.log(foundDevices);
			//console.log(content);

			for(let i = 0; i<content.length; i++) {
				preExistingDevices.push(content[i]);
			}
			//console.log(preExistingDevices);

			// And we can finally begin to build our CB/Tenant/Paths/Devices tree
			buildD3Tree();

		},
		error: function (data) {
			console.log("ERROR in get_all_ext_devices_in_iot_dir: " + JSON.stringify(data));
		}
	});
}

function buildD3Tree(){

	const data = getD3HierarchyFromData();

// const pi = Math.PI;
	const width = 932;
	const root = d3.hierarchy(data);
	const dx = 10;
	const dy = width / (root.height + 1);
	const tree = d3.tree().nodeSize([dx, dy]);

	tree(root);

	let x0 = Infinity;
	let x1 = -x0;
	root.each(d => {
		if (d.x > x1) x1 = d.x;
		if (d.x < x0) x0 = d.x;
	});

	const svg = d3
		.select(document.getElementsByTagName("svg")[0])
		.style("width", "100%")
		.style("height", "auto");

	const g = svg
		.append("g")
		.attr("font-family", "sans-serif")
		.attr("font-size", 10)
		.attr("transform", `translate(${dy / 3},${dx - x0})`);

	const link = g
		.append("g")
		.attr("fill", "none")
		.attr("stroke", "#555")
		.attr("stroke-opacity", 0.4)
		.attr("stroke-width", 1.5)
		.selectAll("path")
		.data(root.links())
		.enter()
		.append("path")
		.attr(
			"d",
			d3
				.linkHorizontal()
				.x(d => d.y)
				.y(d => d.x)
		);

	const node = g
		.append("g")
		.selectAll("g")
		.data(root.descendants())
		.enter()
		.append("g")
		.attr("transform", d => `translate(${d.y},${d.x})`);

	node
		.append("circle")
		.attr("fill", d => (d.children ? "#666" : "#333"))
		.attr("r", 2.5);

	node
		.append("text")
		.attr("dy", "0.31em")
		.attr("x", d => (d.children ? -6 : 6))
		.attr("text-anchor", d => (d.children ? "end" : "start"))
		.text(d => d.data.name)
		.select(function() {
			return this.parentNode.insertBefore(this.cloneNode(true), this);
		})
		.attr("stroke", d => ((d.data.preExisting!=undefined && d.data.preExisting==false)?"green":"white"))
		.attr("stroke-linejoin", "round")
		.attr("stroke-width", 3);

	$('#app').show();
	$('#statusLabel').hide();
	$('#colorsHint').show();
}

function getD3HierarchyFromData(){
	// D3 hierarchy is just a Json structure with parameters "name" and "children"
	var hierarchy = new Object({name: ""});
	/*
	hierarchy.name = "Topolino";
	hierarchy.children = [];
	hierarchy.children.push({ name: "Qui"}, { name: "Quo"}, { name: "Qua"});
	 */
	let brokers = [];
	for(let i =0;i<cbs.length;i++){
		let cb = new Object({name: cbs[i].name});
		let tenants = [];
		for(let j=0;j<cbs[i].tenants.length;j++){
			let tenant = new Object({name: cbs[i].tenants[j].name!=""?cbs[i].tenants[j].name:"ORION ROOT"});
			let paths = [];

			let pathsToScan = cbs[i].tenants[j].servicePaths;

			// now we take a look at the paths:
			// we will have a list of paths like:
			// path1/path1_1/path1_1_1, path1/path1_1/path1_1_2, path1/path1_2, etc.
			// from which we have to build the same name/children structure as above,
			// so we will have only one path1 with only two children path1_1 and path1_2,
			// and path1_1 will have only two children path1_1_1 and path1_1_2.
			for(let k=0;k<pathsToScan.length;k++){
				let currentPath = pathsToScan[k];
				if(currentPath!=""){
					let pathSplit = currentPath.split("/");

					let tempPaths = paths;
					for(let w = 0; w<pathSplit.length; w++){
						let pathNames = [];
						for(let x=0;x<tempPaths.length;x++){
							pathNames.push(tempPaths[x].name);
						}
						if(!pathNames.includes(pathSplit[w])){
							let path = new Object({name:pathSplit[w], children: []});
							tempPaths.push(path);
							pathNames.push(path.name);
							tempPaths=path.children;
						}else{
							for(let x = 0; x<tempPaths.length;x++){
								if(tempPaths[x].name == pathSplit[w]){
									tempPaths = tempPaths[x].children;
								}
							}
						}
					}
				}
			}
			//console.log(cb.name+", "+tenant.name+": ");
			//console.log("Paths: "+paths);

			tenant.children = paths;
			tenants.push(tenant);
		}
		cb.children = tenants;
		brokers.push(cb);
	}
	hierarchy.children = brokers;

	//console.log(hierarchy);

	// Now we just populated the tree with all CBs, tenants, and paths.
	// At this point we have to put found devices in the tree
	// and check which ones are already in the IOT Directory
	putDevicesInTree(hierarchy);

	// TODO it would be nice if the tree shows in grey devices that are in the
	// IOT Directory but not on the Context Broker.

	// TODO Add the feature to click on a green device to edit its parameters and to add it
	// directly to the IOT Directory

	return hierarchy;
}

function putDevicesInTree(tree){
	console.log("Devices:")
	console.log(foundDevices);
	console.log(preExistingDevices);

	let ptr;
	for(let i =0;i<foundDevices.length;i++){
		for(let j=0;j<tree.children.length;j++){
			if(tree.children[j].name==foundDevices[i].contextBroker){
				for(let k=0;k<tree.children[j].children.length;k++){
					if(tree.children[j].children[k].name==foundDevices[i].service || (tree.children[j].children[k].name=="ORION ROOT" && foundDevices[i].service=="")){
						ptr=tree.children[j].children[k].children;
						//entered in tenant
						let devicePath = foundDevices[i].servicePath;
						if(devicePath.length>0 && devicePath.charAt(0)=="/"){
							devicePath=devicePath.substring(1,devicePath.length);
						}
						let devicePathSplit = devicePath.split("/");
						for(let w = 0;w<devicePathSplit.length;w++){
							for(let x = 0; x<ptr.length;x++){
								if(ptr[x].name==devicePathSplit[w]){
									ptr=ptr[x].children;
								}
							}
						}
						// check if devices already exixts in the IOT Directory
						let deviceToPut = new Object({name: foundDevices[i].id, preExisting: false});
						for(let w=0;w<preExistingDevices.length;w++) {
							if (preExistingDevices[w].contextBroker == foundDevices[i].contextBroker &&
								preExistingDevices[w].id == foundDevices[i].id &&
								preExistingDevices[w].service == foundDevices[i].service &&
								preExistingDevices[w].servicePath == foundDevices[i].servicePath &&
								preExistingDevices[w].type == foundDevices[i].type) {
									deviceToPut.preExisting = true;
									break;
							}
						}
						ptr.push(deviceToPut);
					}
				}
			}
		}
	}
}


$(document).ready(function () {
	$('#startDiscoveryButton').prop('disabled', false);
	$('#statusLabel').hide();
	$('#colorsHint').hide();

	//Titolo Default
	if (titolo_default != ""){
		$('#headerTitleCnt').text(titolo_default);
	}

	if (access_denied != ""){
		alert('You need to log in with the right credentials before to access to this page!');
	}

	///// SHOW FRAME PARAMETER USE/////
	if (nascondi == 'hide'){
		$('#mainMenuCnt').hide();
		$('#title_row').hide();
		$('#mainCnt').removeClass('col-md-10');
		$('#mainCnt').addClass('col-md-12');
	}

	$('#sessionExpiringPopup').css("top", parseInt($('body').height() - $('#sessionExpiringPopup').height()) + "px");
	$('#sessionExpiringPopup').css("left", parseInt($('body').width() - $('#sessionExpiringPopup').width()) + "px");

	setInterval(function () {
		var now = parseInt(new Date().getTime() / 1000);
		var difference = sessionEndTime - now;

		if (difference === 300) {
			$('#sessionExpiringPopupTime').html("5 minutes");
			$('#sessionExpiringPopup').show();
			$('#sessionExpiringPopup').css("opacity", "1");
			setTimeout(function () {
				$('#sessionExpiringPopup').css("opacity", "0");
				setTimeout(function () {
					$('#sessionExpiringPopup').hide();
				}, 1000);
			}, 4000);
		}

		if (difference === 120) {
			$('#sessionExpiringPopupTime').html("2 minutes");
			$('#sessionExpiringPopup').show();
			$('#sessionExpiringPopup').css("opacity", "1");
			setTimeout(function () {
				$('#sessionExpiringPopup').css("opacity", "0");
				setTimeout(function () {
					$('#sessionExpiringPopup').hide();
				}, 1000);
			}, 4000);
		}

		if ((difference > 0) && (difference <= 60)) {
			$('#sessionExpiringPopup').show();
			$('#sessionExpiringPopup').css("opacity", "1");
			$('#sessionExpiringPopupTime').html(difference + " seconds");
		}

		if (difference <= 0) {
			location.href = "logout.php?sessionExpired=true";
		}
	}, 1000);

	$('#mainContentCnt').height($('#mainMenuCnt').height() - $('#headerTitleCnt').height());

	$(window).resize(function () {
		$('#mainContentCnt').height($('#mainMenuCnt').height() - $('#headerTitleCnt').height());
	});
});