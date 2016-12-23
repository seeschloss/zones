<?php
// vim:ft=html

if (isset($_POST['region']) && isset($_POST['departement']) && is_array($_POST['departement'])) {
	$file = __DIR__.'/votes.tsv';

	$region = str_replace("\t", "", $_POST['region']);
	$departements = str_replace("\t", "", join(",", $_POST['departement']));

	$ip = $_SERVER['REMOTE_ADDR'];
	$date = date("Y-m-d H:i:s");
	$ua = str_replace("\t", "", $_SERVER['HTTP_USER_AGENT']);
	$action = $_POST['action'];

	$line = "{$region}\t{$departements}\t{$ip}\t{$date}\t{$ua}\t{$action}";

	$line = str_replace("\n", '\n', $line);

	file_put_contents($file, $line."\n", FILE_APPEND | LOCK_EX);
	header("Location: /zones");
	die();
}

?>
<!DOCTYPE html>
<head>
<meta name="viewport" content="width=700, maximum-scale=1.0">
<style>

body {
	text-align: center;
	margin: 0;
	position: relative;
}

svg, canvas {
	display: inline-block;
}

.border-outer {
	stroke: lightblue;
	stroke-width: 8px;
	fill: none;
}
.border {
	stroke: #449;
	stroke-width: 1px;
	fill: lightyellow;
	cursor: help;
}

.departement {
	fill: white;
	stroke: grey;
	cursor: pointer;
}

.departement.hover {
	fill: #DDD;
	stroke-width: 2px;
}

.departement.selected {
	fill: #ABCDEF;
}

#credits {
	position: absolute;
	bottom: 2ex;
	right: 2ex;
}

h1 {
	margin-top: 3px;
	margin-bottom: 2px;
	font-size: 150%;
}

h1.small {
	font-size: 80%;
	font-weight: normal;
	margin: 3px 2em;
}

h2 {
	margin-top: 1ex;
	font-size: 200%;
}

p {
	margin: 0;
}

button {
	display: inline-block;
	padding: 1ex;
	margin: 0.5ex;
	font-size: 200%;
}

button#submit {
	color: #004422;
}

button#pass {
	color: #441111;
	margin-bottom: 2em;
}

</style>

<title>Régions de France, sondage.</title>
<meta name="twitter:card" content="summary" />
<meta name="twitter:site" content="@badaseong" />

<meta property="og:site_name" content="Ssz.fr">
<meta property="og:type" content="website" />
<meta property="og:url" content="https://ssz.fr/zones">
<meta property="og:title" content="Régions de France" />
<meta property="og:image" content="https://ssz.fr/zones/zones-small.png" />
<meta property="og:image:height" content="293" />
<meta property="og:image:width"  content="300" />
<meta property="og:image:type" content="image/png" />
<meta property="og:locale" content="fr_FR" />
<meta property="og:description" content="Sondage sur les régions de France" />
<meta        name="description" content="Sondage sur les régions de France" />

</head>
<body>

<script src="d3.v3.min.js"></script>
<script src="topojson.v1.js"></script>
<script src="queue.v1.min.js"></script>
<script src="francedom.js"></script>

<script>
var regions = [
	'Ouest',
	'Est',
	'Grand Ouest',
	'Sud-Ouest',
	'Nord',
	'Sud',
	'Sud-Est',
	'Région parisienne',
	'Midi',
	'Bretagne',
	'Centre',
	'Provence',
	'Normandie',
	'Alpes',
	'Val de Loire',
	'Vallée du Rhône',
	'Alpes',
	'Bourgogne',
	'Normandie',
	'Gascogne'
];

if (localStorage) {
	var regions_answered = localStorage.getItem('regions-answered');
	try {
		regions_answered = JSON.parse(regions_answered);
	} catch (e) {
		regions_answered = false;
	}

	if (!regions_answered || !(regions_answered instanceof Array)) {
		regions_answered = [];
	}
}

var region = regions[Math.floor(Math.random() * regions.length)];
while (regions_answered.indexOf(region) >= 0 && regions_answered.length < regions.length) {
	region = regions[Math.floor(Math.random() * regions.length)];
}

</script>

<h1 id="question">Votre navigateur semble ne pas supporter JavaScript, c'est indispensable pour répondre à ce questionnaire</h1>
<h1 class="small"></h1>
<h2 id="nom-region">(ou alors il aurait fallu que je fasse vachement plus d'efforts que ce que j'étais prêt à faire)</h2>
<script>

var title = document.querySelector('h1#question');
title.innerHTML = "Que représente pour vous la zone géographique suivante ?";
document.querySelector("h1.small").innerHTML = "Sélectionnez les départements qui vous semblent correspondre à cette zone, ce n'est pas un quizz mais un sondage, il n'y a pas de bonnes ou de mauvaises réponses";

var nom_region = document.querySelector('#nom-region');
nom_region.innerHTML = region;

if (regions_answered.length >= regions.length) {
	title.innerHTML = "Vous avez déjà répondu à toutes les régions proposées, merci&thinsp;!<br />Vous pouvez voir un <a href='stats.php'>premier aperçu des résultats ici</a> en attendant mieux.<br /><br />Sinon, vous pouvez toujours continuer à répondre&thinsp;:";
}

</script>

<div id="credits">
	<p><a href="mailto:see@seos.fr">Contact</a> // <a href="//ssz.fr/">Accueil</a></p>
</div>

<svg></svg>
<form method="post">
	<button name="answer" value="ok" id="submit" type="submit">OK, ça me semble pas mal</button>
	<button name="answer" value="dunno" id="pass" type="submit">Aucune idée de ce que c'est</button>
</form>

<script>

var width = 700,
    height = 700;

var projection = d3.geo.franceDom()
	.scale(4000)
	.translate([390, 340]);

var projectionCorse = d3.geo.franceDom()
	.scale(4000)
	.translate([300, 340]);

var svg = d3.select("svg")
	.attr("width", width)
	.attr("height", height)
	.style("z-index", "-1");

var path = d3.geo.path()
	.projection(projection);

var mousedown = false;
var toggle = 1;

document.body.onmouseleave = function() {
	mousedown = false;
};

var departement = function(element, f) {
	var code = 0;
	if (!element) {
		return;
	}

	if (element.dataset) {
		code = element.dataset.code;
	} else if (element.attributes) {
		code = element.attributes['data-code'].value;
	}

	var d = document.querySelectorAll('.FR' + code);
	for (var i = 0; i < d.length; i++) {
		f.call(d.item(i));
	}
}

var dptHover = function(d) {
	d3.event.stopPropagation();
	d3.event.preventDefault();

	departement(this, function() { this.classList.add('hover'); });

	if (mousedown) {
		if (toggle == 1) {
			departement(this, function() { this.classList.add('selected'); });
		} else {
			departement(this, function() { this.classList.remove('selected'); });
		}
	}
};

var dptOut = function(d) {
	d3.event.stopPropagation();
	d3.event.preventDefault();

	departement(this, function() { this.classList.remove('hover'); });
};

var dptDown = function(d) {
	d3.event.stopPropagation();
	d3.event.preventDefault();

	departement(this, function() { this.classList.toggle('selected'); });
	mousedown = true;
	toggle = this.classList.contains('selected');
};

var dptUp = function(d) {
	d3.event.stopPropagation();
	d3.event.preventDefault();

	mousedown = false;
};

queue()
    .defer(d3.json, "departements.topojson")
    .await(function(error, departements) {
		d3.map(departements.objects).forEach(function(i, d) {
			var topo = topojson.feature(departements, d).features.filter(function(d) {
				return !d.properties.gn_a1_code.match(/^(TF|-1)/) && d.properties.gn_a1_code.match(/^FR.[0-9][0-9AB]$/);
			});

			svg.selectAll(".departement")
				.data(topo.filter(function(d) { return !d.properties.gn_a1_code.match(/(2A|2B)/); })).enter().append('path')
					.attr("class", function(d) { return "departement " + d.properties.gn_a1_code.replace('.', ''); })
					.attr("data-code", function(d) { return d.properties.gn_a1_code.substr(3); })
					.attr("d", path)
					.on('mouseover', dptHover)
					.on('mouseout', dptOut)
					.on('mousedown', dptDown)
					.on('mouseup', dptUp)
					.append('title')
						.text(function(d) { return d.properties.name; })

			var paris_path = d3.geo.path()
				.projection(projection.paris);

			svg.selectAll(".paris")
				.data(topo.filter(function(d) { return d.properties.gn_a1_code.match(/(75|92|93|94)/); })).enter().append('path')
					.attr("class", function(d) { return "departement paris " + d.properties.gn_a1_code.replace('.', ''); })
					.attr("data-code", function(d) { return d.properties.gn_a1_code.substr(3); })
					.attr("d", paris_path)
					.on('mouseover', dptHover)
					.on('mouseout', dptOut)
					.on('mousedown', dptDown)
					.on('mouseup', dptUp)
					.append('title')
						.text(function(d) { return d.properties.name; })

			var corse_path = d3.geo.path()
				.projection(projectionCorse);

			svg.selectAll(".corse")
				.data(topo.filter(function(d) { return d.properties.gn_a1_code.match(/(2A|2B)/); })).enter().append('path')
					.attr("class", function(d) { return "departement corse " + d.properties.gn_a1_code.replace('.', ''); })
					.attr("data-code", function(d) { return d.properties.gn_a1_code.substr(3); })
					.attr("d", corse_path)
					.on('mouseover', dptHover)
					.on('mouseout', dptOut)
					.on('mousedown', dptDown)
					.on('mouseup', dptUp)
					.append('title')
						.text(function(d) { return d.properties.name; })

			svg
				.on('touchstart', function() {
					var element = document.elementFromPoint(d3.event.touches[0].clientX, d3.event.touches[0].clientY);

					if (element && element.tagName == 'path') {
						d3.event.stopPropagation();
						d3.event.preventDefault();

						mousedown = true;

						departement(element, function() { this.classList.toggle('selected'); });
						toggle = element.classList.contains('selected');
					}
				})
				.on('touchmove', function() {
					var element = document.elementFromPoint(d3.event.touches[0].clientX, d3.event.touches[0].clientY);
					if (element && element.tagName == 'path') {
						d3.event.stopPropagation();
						d3.event.preventDefault();

						if (mousedown) {
							if (toggle == 1) {
								departement(element, function() { this.classList.add('selected'); });
							} else {
								departement(element, function() { this.classList.remove('selected'); });
							}
						}
					}
				})
				.on('touchend', function() {
					if (d3.event.cancellable) {
						d3.event.stopPropagation();
						d3.event.preventDefault();
					}

					mousedown = false;
				})
		});
});

var submitHandler = function(e) {
	var selected = document.querySelectorAll('.departement.selected');
	var codes = [];
	for (var i = 0; i < selected.length; i++) {
		var code = 0;
		var element = selected.item(i);

		if (element.dataset) {
			code = element.dataset.code;
		} else if (element.attributes) {
			code = element.attributes['data-code'].value;
		}

		codes.push(code);
	}

	var xhr = new XMLHttpRequest();
	xhr.open("post", "?submit", false);

	var data = [];
	for (var i in codes) {
		data.push("departement[]=" + codes[i]);
	}

	if (this.id == "pass") {
		data.push("action=pass");
	} else {
		data.push("action=ok");
	}

	var post = "region=" + region + "&" + data.join('&');

	xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

	xhr.send(post);

	if (localStorage) {
		regions_answered.push(region);
		regions_answered = regions_answered.filter(function(value, i, self) { return self.indexOf(value) === i; });
		localStorage.setItem('regions-answered', JSON.stringify(regions_answered));
	}

	e.preventDefault();
	document.location.href = document.location.href;
};
document.querySelector('#submit').onclick = submitHandler;
document.querySelector('#pass').onclick = submitHandler;

</script>
</body>
