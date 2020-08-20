var _serviceIP = "../stubs";

var cbs = [];
var foundDevices = [];
var preExistingDevices = [];
var numOfGetRequests = 0;
var numOfReturnedGetRequests = 0;
var currentEditId="";
var editDeviceConditionsArray=[];
var gb_datatypes ="";
var gb_value_units ="";
var gb_value_types = "";

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

$.ajax({url: "../api/contextBrokerRetrieval_e.php",
	data: {
		action: 'get_param_values',
		organization:organization
	},
	type: "POST",
	async: true,
	dataType: 'json',
	success: function (mydata)
	{
		gb_datatypes= mydata["data_type"];
		gb_value_units= mydata["value_unit"];
		gb_value_types= mydata["value_type"];
		//console.log(mydata);
	},
	error: function (mydata)
	{
		console.log(JSON.stringify(mydata));
		alert("Network errors. <br/> Get in touch with the Snap4City Administrator<br/>"+ JSON.stringify(mydata));
	}
});

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

	const width = 960;
	const dx = 30;
	const margin = new Object({top: 48, right: 0, bottom: 48, left: 24});
	const dy = (width / 6) - margin.left - margin.right;
	const tree = d3.tree().nodeSize([dx, dy]);
	const diagonal = d3.linkHorizontal().x(d => d.y).y(d => d.x);
	const root = d3.hierarchy(data);

	root.x0 = dy / 2;
	root.y0 = 0;
	root.descendants().forEach((d, i) => {
		d.id = i;
		d._children = d.children;
	});

	const svg = d3.select(document.getElementsByTagName("svg")[0]);
	svg.selectAll("*").remove();
	svg.attr("id", "chart")
		.attr("viewBox", [-margin.left, -margin.top, width, dx])
		.attr("font-family", '"Work Sans", "Raleway", "Helvetica Neue", Helvetica, sans-serif')
		.attr("font-size", 10)
		.style("user-select", "none");

	const gLink = svg.append("g")
		.attr("id", "tree-links")
		.attr("fill", "none")
		.attr("stroke", "#202630")
		.attr("stroke-opacity", 0.4)
		.attr("stroke-width", 1.5);

	const gNode = svg.append("g")
		.attr("id", "tree-nodes")
		.attr("pointer-events", "all");

	function update(source) {
		const duration = d3.event && d3.event.altKey ? 1000 : 200;
		const nodes = root.descendants().reverse();
		const links = root.links();

		// Compute the new tree layout
		tree(root);

		let left = root;
		let right = root;
		root.eachBefore(node => {
			if (node.x < left.x) left = node;
			if (node.x > right.x) right = node;
		});

		const height = right.x - left.x + margin.top + margin.bottom;

		const transition = svg.transition()
			.duration(duration)
			.attr("viewBox", [-margin.left, left.x - margin.top, width, height])
			.tween("resize", window.ResizeObserver ? null : () => () => svg.dispatch("toggle"));

		svg.selectAll("#source")
			.transition(transition)
			.attr("x", -margin.left)
			.attr("y", height + (left.x - margin.top))
			.attr("dy", "-1em");

		// Update the node data
		const node = gNode.selectAll("g")
			.data(nodes, d => d.id)
			.join(
				enter => { // Enter any new nodes at the parent's previous position.
					const nodeEnter = enter.append("g")
						.attr("class", "tree-nodes-dots")
						.attr("transform", d => `translate(${source.y0},${source.x0})`)
						.attr("fill-opacity", 0)
						.attr("stroke-opacity", 0)
						.on("click", d => {
							//se è un path apri i children
							d.children = d.children ? null : d._children;
							//se è un device "verde" apre il dialog per l'edit
							if(d.data.preExisting!=undefined && d.data.preExisting==false){
								openEditDialog(d);
							}
							update(d);
						});

					nodeEnter.append("circle")
						.attr("r", 6.5)
						.attr("stroke", d => d._children ? "#3c72d7" : "#949494")
						.attr("stroke-width", 2)
						.attr("fill", d => ((d.data.preExisting!=undefined && d.data.preExisting==false)?"green":"white"))
						.attr("cursor", d => (d._children || (d.data.preExisting!=undefined && d.data.preExisting==false)) ? "pointer" : "default");

					nodeEnter.append("text")
						.attr("fill", d => ((d.data.preExisting!=undefined && d.data.preExisting==false)?"white":"black"))
						.attr("class", "tree-nodes-label")
						.attr("dy", "0.5em")
						.attr("x", d => d._children ? -6 : 6)
						.attr("text-anchor", d => d._children ? "end" : "start")
						.attr("font-size", d => d.depth === 0 ? 20: 16)
						.attr("cursor", d => (d._children || (d.data.preExisting!=undefined && d.data.preExisting==false)) ? "pointer" : "default")
						.text(d => d.data.name)
						.clone(true).lower()
						.attr("aria-hidden", "true") // hide duplicate text from screen readers / assistive tech
						.style("user-select", "none")
						.attr("stroke", d => ((d.data.preExisting!=undefined && d.data.preExisting==false)?"green":"white"))
						.attr("stroke-linejoin", "round")
						.attr("stroke-width", 3);

					return nodeEnter;
				},
				update => update,
				exit => { // Transition exiting nodes to the parent's new position
					exit.transition(transition).remove()
						.attr("transform", d => `translate(${source.y},${source.x})`)
						.attr("fill-opacity", 0)
						.attr("stroke-opacity", 0);
					return exit;
				}
			);

		// Transition nodes to their new position
		node.transition(transition)
			.attr("transform", d => `translate(${d.y},${d.x})`)
			.attr("fill-opacity", 1)
			.attr("stroke-opacity", 1);

		// Update the links
		const link = gLink.selectAll("path")
			.data(links, d => d.target.id)
			.join(
				enter => { // Enter any new links at the parent's previous position
					const enterLink = enter.append("path")
						.attr("d", d => {
							const o = {x: source.x0, y: source.y0};
							return diagonal({source: o, target: o});
						});
					return enterLink;
				},
				update => update,
				exit => { // Transition exiting nodes to the parent's new position
					exit.transition(transition).remove()
						.attr("d", d => {
							const o = {x: source.x, y: source.y};
							return diagonal({source: o, target: o});
						});
				}
			);

		// Transition links to their new position
		link.transition(transition)
			.attr("d", diagonal);

		// Stash the old positions for transition
		root.eachBefore(d => {
			d.x0 = d.x;
			d.y0 = d.y;
		});
	}

	update(root);

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
						let deviceToPut = new Object({name: foundDevices[i].id, preExisting: false,
							contextBroker: foundDevices[i].contextBroker,
							latitude: foundDevices[i].latitude, longitude: foundDevices[i].longitude,
							service: foundDevices[i].service, servicePath: foundDevices[i].servicePath,
							type: foundDevices[i].type});
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

function openEditDialog(deviceData) {
	$("#editDeviceModalBody").show();
	$('#editDeviceModalTabs').show();

	$("#editDeviceLoadingMsg").hide();
	$("#editDeviceLoadingIcon").hide();
	$("#editDeviceOkMsg").hide();
	$("#editDeviceOkIcon").hide();
	$("#editDeviceKoMsg").hide();
	$("#editDeviceKoIcon").hide();
	$("#editDeviceModalFooter").show();
	$("#editDeviceModalLabel").html("Adding device - " + deviceData.data.name);
	$("#editDeviceModal").modal('show');

	//console.log(deviceData);

	// fill dialog parameters with devices ones
	var id = deviceData.id;

	if (currentEditId!==id) {
		//if the user changed the device to edit, clean the list of value and update the currentEditId
		document.getElementById('editlistAttributes').innerHTML = "";
		document.getElementById('addlistAttributesM').innerHTML = "";
		document.getElementById('deletedAttributes').innerHTML = "";
		currentEditId = id;

		deviceData=deviceData.data;

		let deviceName = deviceData.name;
		let contextbroker = deviceData.contextBroker;
		let type = deviceData.type;
		let latitude = deviceData.latitude;
		let longitude = deviceData.longitude;
		let service = deviceData.service;
		let servicePath = deviceData.servicePath;

		$('#inputNameDeviceM').prop("disabled", true);	$('#inputNameDeviceM').val(deviceName);
		$('#deviceCB').prop("disabled", true);	$('#deviceCB').val(contextbroker);
		$('#inputTypeDeviceM').val(type);
		$('#selectProtocolDeviceM').val("ngsi w/MultiService");
		$('#selectFormatDeviceM').val("json");
		$('#inputLatitudeDeviceM').val(latitude.value);
		$('#inputLongitudeDeviceM').val(longitude.value);
		$('#deviceService').prop("disabled", true);	$('#deviceService').val(service);
		$('#editInputServicePathDevice').prop("disabled", true);	$('#editInputServicePathDevice').val(servicePath);
	}
	$('#editDeviceModal').show();
}

function editGenerateKeysCLicked(){
	var k1= generateUUID();
	var k2= generateUUID();
	$("#KeyOneDeviceUserM").val(k1);
	$("#KeyTwoDeviceUserM").val(k2);
}

function generateUUID() { // Public Domain/MIT
	var d = new Date().getTime();
	if (typeof performance !== 'undefined' && typeof performance.now === 'function'){
		d += performance.now(); //use high-precision timer if available
	}
	return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
		var r = (d + Math.random() * 16) % 16 | 0;
		d = Math.floor(d / 16);
		return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
	});
}

$("#addAttrMBtn").click(function(){
	//console.log("#addAttrMBtn");
	content = drawAttributeMenu("","", "", "", "", "", "300"," ",'addlistAttributesM');
	editDeviceConditionsArray['addlistAttributesM'] = true;
	$('#addlistAttributesM').append(content);
});

function drawAttributeMenu
(attrName, data_type, value_type, editable, value_unit, healthiness_criteria, value_refresh_rate,old_value_name, parent)
{
	options="";

	if (value_type!="" && value_type!= undefined) labelcheck= value_type;
	else { //0910Fatima
		labelcheck="";
		options += "<option value=' ' selected> </option>";
	}
	for (let n=0; n < gb_value_types.length; n++)
	{
		if (labelcheck == gb_value_types[n].value)
			options += "<option value=\""+gb_value_types[n].value+"\" selected>"+ gb_value_types[n].label+ "</option>";
		else options += "<option value=\""+gb_value_types[n].value+"\">"+ gb_value_types[n].label+ "</option>";
	}

	myunits="";// <option value=\"none\"></option>";
	if (value_unit!="" && value_unit != undefined && value_unit != null){
		labelcheck= value_unit;
	}else {
		labelcheck="";
		myunits += "<option value=' ' selected> </option>";
	}
	for (let n=0; n < gb_value_units.length; n++)
	{
		if (labelcheck == gb_value_units[n].value)
			myunits += "<option value=\""+gb_value_units[n].value+"\" selected>"+ gb_value_units[n].label+ "</option>";
		else myunits += "<option value=\""+gb_value_units[n].value+"\">"+ gb_value_units[n].label+ "</option>";
	}

	//---start sara---
	if(value_refresh_rate===undefined){
		value_refresh_rate= "";
	}
	var refresh_rate="", different_values="", within_bounds="", healthiness_empty=""; //0910Fatima
	switch(healthiness_criteria){
		case "refresh_rate": refresh_rate ="selected";
			break;
		case "different_values":
			different_values="selected";
			break;
		case "within_bounds":
			within_bounds="selected";
			break;
		default: //0910Fatima
			healthiness_empty="selected";
			break;
	}

	//0910Fatima--block modification start
	var editable_true="",editable_false="", editable_empty="";
	if(editable=="1"){
		editable_true="selected";
	}
	else if (editable=="0"){
		editable_false="selected";
	}
	else{
		editable_empty="selected";
	}
	//0910Fatima--block modification end

	//---end sara

	mydatatypes="";
	if (data_type!="" && data_type != undefined) {
		labelcheck= data_type;
	}
	else { //0910Fatima
		labelcheck="";
		mydatatypes += "<option value=' ' selected> </option>";
	}

	for (let n=0; n < gb_datatypes.length; n++)
	{
		if (labelcheck == gb_datatypes[n])
			mydatatypes += "<option value=\""+gb_datatypes[n]+"\" selected>"+ gb_datatypes[n]+ "</option>";
		else mydatatypes += "<option value=\""+gb_datatypes[n]+"\">"+ gb_datatypes[n]+ "</option>";
	}
	console.log(data_type +","+ value_type+","+ editable+","+ value_unit+","+ healthiness_criteria+","+ value_refresh_rate+","+ parent);
	return "<div class=\"row\" style=\"border:3px solid blue;\" ><div class=\"col-xs-6 col-md-3 modalCell\">" +
		"<div class=\"modalFieldCnt\"><input type=\"text\" class=\"modalInputTxt\""+
		"name=\"" +  attrName +  "\"  value=\"" + attrName + "\">" +
		"</div><div class=\"modalFieldLabelCnt\">Value Name</div></div>"+

		"<div class=\"col-xs-6 col-md-3 modalCell\"><div class=\"modalFieldCnt\">"+
		"<select class=\"modalInputTxt\" name=\""+ attrName+"-type" +
		"\">" + mydatatypes +
		"</select></div><div class=\"modalFieldLabelCnt\">Data Type</div></div>" +

		"<div class=\"col-xs-6 col-md-3 modalCell\"><div class=\"modalFieldCnt\">" +
		"<select class=\"modalInputTxt\" name=\""+ value_type +
		"\">" + 		 options +
		"</select></div><div class=\"modalFieldLabelCnt\">Value Type</div></div>" +

		"<div class=\"col-xs-6 col-md-3 modalCell\"><div class=\"modalFieldCnt\">" +
		"<select class=\"modalInputTxt\" name=\""+ editable +
		"\">" +
		"<option value='0' "+editable_false+">false</option>" +
		"<option value='1' "+editable_true+">true</option> </select>" +
		"<option value='' "+editable_empty+"> </option> </select>" + //0910Fatima
		"</div><div class=\"modalFieldLabelCnt\">Editable</div></div>"+

		"<div class=\"col-xs-6 col-md-3 modalCell\"><div class=\"modalFieldCnt\">" +
		"<select class=\"modalInputTxt\" name=\""+ value_unit +
		"\">" +
		myunits +
		"</select></div><div class=\"modalFieldLabelCnt\">Value Unit</div></div>"+

		"<div class=\"col-xs-6 col-md-3 modalCell\"><div class=\"modalFieldCnt\">" +
		"<select class=\"modalInputTxt\" name=\"" + healthiness_criteria +
		"\" \>"+
		"<option value=\"refresh_rate\" "+refresh_rate+">Refresh rate</option>" +
		"<option value=\"different_values\" "+different_values+">Different Values</option>" +
		"<option value=\"within_bounds\" "+within_bounds+">Within bounds</option>" +
		"<option value= ' '"+healthiness_empty+"> </option>" +

		"</select></div><div class=\"modalFieldLabelCnt\">healthiness criteria</div></div>"+

		"<div class=\"col-xs-6 col-md-3 modalCell\"><div class=\"modalFieldCnt\">" +
		"<input type=\"text\" class=\"modalInputTxt\" name=\""+ value_refresh_rate +
		"\" value=\"" + value_refresh_rate + "\"></div><div class=\"modalFieldLabelCnt\">healthiness value</div></div>"+
		//sara start
		"<div class=\"col-xs-6 col-md-3 modalCell\"><div class=\"modalFieldCnt\">" +
		"<input type=\"hidden\"  name=\""+ old_value_name +
		"\" value=\"" + old_value_name + "\"></div></div>"+
		//sara end
		"<div class=\"col-xs-6 col-md-3 modalCell\"><div class=\"modalFieldCnt\">"+
		//+"<i class=\"fa fa-minus-square\" onclick=\"removeElementAt('" + parent + "',this); return true;\"  style=\"font-size:36px; color: #ffcc00\"></i></div></div></div>";
		"<button class=\"btn btn-warning\" onclick=\"removeElementAt('" + parent + "',this);return true;\">Remove Value</button></div></div></div>";
	/*	+
   "<div class=\"newButton modalCell\"> <button class=\"btn btn-warning\" onclick=\"generateMissingValue('" + parent + "',this); return true;\">Predict Value</button></div></div></div>"
		;*/

}

function removeElementAt(parent,child) {
	var list = document.getElementById(parent);
	// var content = child.parentElement.parentElement.parentElement.innerHTML
	// console.log("elemento cancellato " + document.getElementById('deletedAttributes').innerHTML);
	if (parent=="editlistAttributes")
	{     document.getElementById('deletedAttributes').appendChild(child.parentElement.parentElement.parentElement);}
	else list.removeChild(child.parentElement.parentElement.parentElement);
}

$('#editDeviceConfirmBtn').off("click");
$('#editDeviceConfirmBtn').click(function(){
	mynewAttributes = [];
	var regex=/[^a-z0-9:._-]/gi;
	var someNameisWrong=false;
	let num1 = document.getElementById('addlistAttributesM').childElementCount;
	for (var m=0; m< num1; m++)
	{
		var newatt= {value_name: document.getElementById('addlistAttributesM').childNodes[m].childNodes[0].childNodes[0].childNodes[0].value.trim(),
			data_type:document.getElementById('addlistAttributesM').childNodes[m].childNodes[1].childNodes[0].childNodes[0].value.trim(),
			value_type:document.getElementById('addlistAttributesM').childNodes[m].childNodes[2].childNodes[0].childNodes[0].value.trim(),
			editable:document.getElementById('addlistAttributesM').childNodes[m].childNodes[3].childNodes[0].childNodes[0].value.trim(),
			value_unit:document.getElementById('addlistAttributesM').childNodes[m].childNodes[4].childNodes[0].childNodes[0].value.trim(),
			healthiness_criteria: document.getElementById('addlistAttributesM').childNodes[m].childNodes[5].childNodes[0].childNodes[0].value.trim(),
			healthiness_value: document.getElementById('addlistAttributesM').childNodes[m].childNodes[6].childNodes[0].childNodes[0].value.trim()};

		//console.log("new att:"+JSON.stringify(newatt));

		if (newatt.value_name!=""&& !regex.test(newatt.value_name) && newatt.data_type!="" && newatt.value_type!="" && newatt.editable!="" && newatt.healthiness_criteria!="" && newatt.healthiness_value!="")
			mynewAttributes.push(newatt);
		else
			someNameisWrong=true;
	}

	if(mynewAttributes.length>0 && !someNameisWrong){

		document.getElementById('editlistAttributes').innerHTML = "";
		$("#editDeviceModalTabs").hide();
		$("#editDeviceModalBody").hide();
		$('#editDeviceModal div.modalCell').hide();
		$("#editDeviceModalFooter").hide();
		$("#editDeviceOkMsg").hide();
		$("#editDeviceOkIcon").hide();
		$("#editDeviceKoMsg").hide();
		$("#editDeviceKoIcon").hide();
		$('#editDeviceLoadingMsg').show();
		$('#editDeviceLoadingIcon').show();

		//console.log("LISTA" + JSON.stringify(mynewAttributes));
		var d = new Date();
		var t = d.getTime();
		//console.log("time before the insert request in milliseconds");
		//console.log(t);
		//console.log($('#inputLatitudeDevice'));
		//console.log($('#inputLatitudeDevice').val());
		//console.log($('#selectContextBroker'));

		var service = $('#deviceService').val();
		var servicePath = $('#editInputServicePathDevice').val();

		//console.log($('#selectProtocolDeviceM').val());
		if ($('#selectProtocolDeviceM').val() == "ngsi w/MultiService"){
			// servicePath value pre-processing
			if (servicePath[0] !== "/" || servicePath === "") servicePath = "/" + servicePath;
			if (servicePath[servicePath.length -1] === "/" && servicePath.length > 1) servicePath = servicePath.substr(0, servicePath.length -1);
		}

		//var id = service+"."+servicePath+"."+$.trim($('#inputNameDeviceM').val());
		var id = $.trim($('#inputNameDeviceM').val());

		$.ajax({
			url: "../api/device.php",
			data:{
				action: "insert",
				//Sara2510 - for logging purpose
				username: loggedUser,

				attributes: JSON.stringify(mynewAttributes),
				id: id,
				type: $('#inputTypeDeviceM').val(),
				kind: $('#selectKindDeviceM').val(),
				contextbroker: $('#deviceCB').val(),
				organization : organization,
				protocol: $('#selectProtocolDeviceM').val(),
				format: $('#selectFormatDeviceM').val(),
				mac: $('#inputMacDeviceM').val(),
				model: $('#selectModelDeviceM').val(),
				producer: $('#inputProducerDeviceM').val(),
				latitude: $('#inputLatitudeDeviceM').val(),
				longitude: $('#inputLongitudeDeviceM').val(),
				visibility: $('#selectVisibilityDeviceM').val(),
				frequency: $('#inputFrequencyDeviceM').val(),
				token : sessionToken,
				k1 : $("#KeyOneDeviceUserM").val(),
				k2 : $("#KeyTwoDeviceUserM").val(),
				edgegateway_type : $("#selectEdgeGatewayTypeM").val(),
				edgegateway_uri : $("#inputEdgeGatewayUriM").val(),
				subnature: $('#selectSubnatureM').val(),
				static_attributes: JSON.stringify(retrieveStaticAttributes("addlistStaticAttributes")),
				service : service,
				servicePath : servicePath
			},
			type: "POST",
			async: true,
			dataType: "JSON",
			//timeout: 0,
			success: function (mydata)
			{
				var d = new Date();
				var t = d.getTime();
				//console.log("time after a successful insert request in milliseconds");
				//console.log(t);
				//console.log(mydata["msg"]);
				if(mydata["status"] === 'ko')
				{
					console.log("Error adding Device type");
					console.log(mydata);
					$('#addDeviceLoadingMsg').hide();
					$('#addDeviceLoadingIcon').hide();

					$("#addDeviceModal").modal('hide');


					$('#inputNameDevice').val("");
					$('#inputTypeDevice').val("");
					//$('#selectKindDevice').val(""),
					$('#selectContextBroker').val("NULL");
					$('#inputUriDevice').val("");
					//$('#selectProtocolDeviceM').val("NULL");
					//$('#selectFormatDevice').val("NULL");
					$('#createdDateDevice').val("");
					$('#inputMacDevice').val("");
					$('#selectModelDevice').val("");
					$('#inputProducerDevice').val("");
					$('#inputLatitudeDevice').val("");
					$('#inputLongitudeDevice').val("");
					$('#inputLongitudeDevice').val("");
					$('#selectVisibilityDevice').val("NULL");
					$('#inputFrequencyDevice').val("600");
					$("#KeyOneDeviceUser").val("");
					$("#KeyTwoDeviceUser").val("");
					$("#KeyOneDeviceUserMsg").html("");
					$("#KeyTwoDeviceUserMsg").html("");

					$('#selectSubnature').val("");
					$('#selectSubnature').trigger("change");
					$("#addNewStaticBtn").hide();

					$("#addDeviceKoModal").modal('show');
					$("#addDeviceOkModal").hide();
					if(mydata["error_msg"]!='undefined' && mydata["error_msg"]!="")
						$("#addDeviceKoModalInnerDiv1").html('<h5>Operation failed, due to the following Error: ' + mydata["error_msg"]+ '</h5>');
					else
						$("#addDeviceKoModalInnerDiv1").html('<h5>An error occurred, operation failed.</h5>');

				}
				else if (mydata["status"] === 'ok')
				{
					console.log("Success adding Device");
					//console.log(JSON.stringify(mydata));
					$('#addDeviceLoadingMsg').hide();
					$('#addDeviceLoadingIcon').hide();

					$("#addDeviceModal").modal('hide');


					$('#inputNameDevice').val("");
					$('#inputTypeDevice').val("");
					$('#selectContextBroker').val("NULL");
					$('#inputUriDevice').val("");
					//$('#selectProtocolDeviceM').val("NULL");
					//$('#selectFormatDevice').val("NULL");
					$('#createdDateDevice').val("");
					$('#inputMacDevice').val("");
					$('#selectModelDevice').val("");
					$('#inputProducerDevice').val("");
					$('#inputLatitudeDevice').val("");
					$('#inputLongitudeDevice').val("");
					$('#inputLongitudeDevice').val("");
					$('#selectVisibilityDevice').val("NULL");
					$('#inputFrequencyDevice').val("600");
					$("#KeyOneDeviceUser").val("");
					$("#KeyTwoDeviceUser").val("");
					$("#KeyOneDeviceUserMsg").html("");
					$("#KeyTwoDeviceUserMsg").html("");

					$('#selectSubnature').val("");
					$('#selectSubnature').trigger("change");
					$("#addNewStaticBtn").hide();

					$("#addDeviceOkModal").modal('show');
					$("#addDevicekoModal").hide();

					$("#addDeviceOkModalInnerDiv1").html('<h5>The device has been successfully registered. You can find further information on how to use and set up your device at the following page:</h5>' + "   " + '<h5>https://www.snap4city.org/drupal/node/76</h5>');

					$('#devicesTable').DataTable().destroy();
				}

			},
			error: function (mydata)
			{
				console.log("Error insert device");
				console.log("Error status -- Ko result: " + JSON.stringify(mydata));
				$('#addDeviceLoadingMsg').hide();
				$('#addDeviceLoadingIcon').hide();

				$("#addDeviceModal").modal('hide');


				$('#inputNameDevice').val("");
				$('#inputTypeDevice').val("");
				$('#selectContextBroker').val("NULL");
				$('#inputUriDevice').val("");
				//$('#selectProtocolDeviceM').val("NULL");
				//$('#selectFormatDevice').val("NULL");
				$('#createdDateDevice').val("");
				$('#inputMacDevice').val("");
				$('#selectModelDevice').val("");
				$('#inputProducerDevice').val("");
				$('#inputLatitudeDevice').val("");
				$('#inputLongitudeDevice').val("");
				$('#inputLongitudeDevice').val("");
				$('#selectVisibilityDevice').val("NULL");
				$('#inputFrequencyDevice').val("600");
				$("#KeyOneDeviceUser").val("");
				$("#KeyTwoDeviceUser").val("");
				$("#KeyOneDeviceUserMsg").html("");
				$("#KeyTwoDeviceUserMsg").html("");


				$('#selectSubnature').val("");
				$('#selectSubnature').trigger("change");
				$("#addNewStaticBtn").hide();

				console.log("Error adding Device type");
				console.log(mydata);
				$("#addDeviceKoModal").modal('show');
				$("#addDeviceOkModal").hide();
				if(mydata["error_msg"]!='undefined' && mydata["error_msg"]!="")
					$("#addDeviceKoModalInnerDiv1").html('<h5>Operation failed, due to the following Error: ' + mydata["error_msg"]+ '</h5>');
				else
					$("#addDeviceKoModalInnerDiv1").html('<h5>An error occurred, operation failed.</h5>');
			}
		});

	}
	else{
		alert("Check the values of your device, make sure that data you entered are valid!");
	}
});

function retrieveStaticAttributes(source, all){
	var staticArr = $('#'+source+' div[name="additionalRow"]').find("select");
	var staticArr2=  $('#'+source+' div[name="additionalRow"]').find("input");
	var staticValues = [];
	for(let i = 0; i < staticArr.length; i++){
		if ((staticArr2[i].value)||(all!== undefined)){
			var array = [];
			array.push(staticArr[i].value);
			array.push(staticArr2[i].value);
			staticValues.push(array);
		}
	}
	return staticValues;
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