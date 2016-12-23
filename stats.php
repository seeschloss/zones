<?php

$stats = [];

foreach (file(__DIR__.'/votes.tsv') as $line) {
	list($region, $departements, $ip, $date, $ua, $action) = explode("\t", trim($line));

	if (!isset($stats[$region])) {
		$stats[$region] = [
			'departements' => [],
		];
	}

	foreach (explode(',', $departements) as $departement) {
		if (!isset($stats[$region]['departements'][$departement])) {
			$stats[$region]['departements'][$departement] = 0;
		}

		$stats[$region]['departements'][$departement] += 1;
	}
}

ksort($stats);

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
	font-size: 150%;
}

h2 {
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

.popup {
	pointer-events: none;
	position: absolute;
	display: inline-block;
	background-color: white;
	border: 1px solid #333;
	border-radius: 2px;
	padding: 1ex 1em;
}

.popup .numero:before {
	content: ' (';
}

.popup .numero:after {
	content: ')';
}

</style>

<title>Régions de France, sondage.</title>
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:site" content="@badaseong" />

<meta property="og:site_name" content="Ssz.fr">
<meta property="og:type" content="website" />
<meta property="og:url" content="https://ssz.fr/zones">
<meta property="og:title" content="Régions de France" />
<meta property="og:image" content="http://render.sauf.ca/ssz.fr/zones" />
<meta property="og:image:height" content="768" />
<meta property="og:image:width"  content="1024" />
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

<h1 id="question">Votre navigateur semble ne pas supporter JavaScript, c'est indispensable pour répondre à ce questionnaire</h1>
<h2 id="nom-region">(ou alors il aurait fallu que je fasse vachement plus d'efforts que ce que j'étais prêt à faire)</h2>
<script>

var title = document.querySelector('h1#question');
title.innerHTML = "Sélectionnez une zone pour voir les résultats";
var nom_region = document.querySelector('#nom-region');
nom_region.innerHTML = "";

</script>

<div id="credits">
	<p><a href="mailto:see@seos.fr">Contact</a> // <a href="//ssz.fr/">Accueil</a></p>
</div>

<svg></svg>

<script>

var nom_region = document.querySelector('#nom-region');
nom_region.innerHTML = "";

var results = <?php echo json_encode($stats); ?>

var select = document.createElement('select');
nom_region.appendChild(select);

var region = false;

for (var i in results) {
	if (!region) {
		region = i;
	}

	var option = document.createElement('option');
	option.value = i;
	option.innerHTML = i;
	select.appendChild(option);
}

var color_scale = d3.scale.pow().exponent(2)
	.domain([0, 100])
	.range(['white', 'blue']);

var percent_scale = d3.scale.linear()
	.domain([0, 100])
	.range([0, 100]);

color_scale.domain([0, d3.max(d3.map(results[region].departements).values())]);
percent_scale.domain(color_scale.domain());

var color = function (departement) {
	return color_scale(results[region].departements[departement]);

};

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

var popup = null;
var showPopup = function(e, dpt) {
	// défini plus loin, quand on a les noms de départements
};

var dptHover = function(d) {
	d3.event.stopPropagation();
	d3.event.preventDefault();

	departement(this, function() { this.classList.add('hover'); });

	showPopup(d3.event, this.dataset.code);
};

var dptOut = function(d) {
	d3.event.stopPropagation();
	d3.event.preventDefault();

	departement(this, function() { this.classList.remove('hover'); });

	if (popup) {
		document.body.removeChild(popup);
	}
};

queue()
    .defer(d3.json, "departements.topojson")
    .await(function(error, departements) {
		var noms_departements = {};

		d3.map(departements.objects).forEach(function(i, d) {
			var topo = topojson.feature(departements, d).features.filter(function(d) {
				if (!d.properties.gn_a1_code.match(/^(TF|-1)/) && d.properties.gn_a1_code.match(/^FR.[0-9][0-9AB]$/)) {
					noms_departements[d.properties.gn_a1_code.substr(3)] = d.properties.name;
					return true;
				} else {
					return false;
				}
			});

			svg.selectAll(".departement")
				.data(topo.filter(function(d) { return !d.properties.gn_a1_code.match(/(2A|2B)/); })).enter().append('path')
					.attr("class", function(d) { return "departement " + d.properties.gn_a1_code.replace('.', ''); })
					.attr("data-code", function(d) { return d.properties.gn_a1_code.substr(3); })
					.attr("d", path)
					.style("fill", function(d) { return color(d.properties.gn_a1_code.substr(3)); })
					.on('mouseover', dptHover)
					.on('mouseout', dptOut)

			var paris_path = d3.geo.path()
				.projection(projection.paris);

			svg.selectAll(".paris")
				.data(topo.filter(function(d) { return d.properties.gn_a1_code.match(/(75|92|93|94)/); })).enter().append('path')
					.attr("class", function(d) { return "departement paris " + d.properties.gn_a1_code.replace('.', ''); })
					.attr("data-code", function(d) { return d.properties.gn_a1_code.substr(3); })
					.attr("d", paris_path)
					.on('mouseover', dptHover)
					.on('mouseout', dptOut)

			var corse_path = d3.geo.path()
				.projection(projectionCorse);

			svg.selectAll(".corse")
				.data(topo.filter(function(d) { return d.properties.gn_a1_code.match(/(2A|2B)/); })).enter().append('path')
					.attr("class", function(d) { return "departement corse " + d.properties.gn_a1_code.replace('.', ''); })
					.attr("data-code", function(d) { return d.properties.gn_a1_code.substr(3); })
					.attr("d", corse_path)
					.on('mouseover', dptHover)
					.on('mouseout', dptOut);

			svg
				.on('touchstart', function() {
					var element = document.elementFromPoint(d3.event.touches[0].clientX, d3.event.touches[0].clientY);

					if (element && element.tagName == 'path') {
						d3.event.stopPropagation();
						d3.event.preventDefault();

						departement(element, function() { this.classList.toggle('selected'); });
					}
				})

				select.onchange = function(e) {
					region = this.value;

					color_scale.domain([0, d3.max(d3.map(results[region].departements).values())]);
					percent_scale.domain(color_scale.domain());

					svg.selectAll(".departement")
						.style("fill", "white")
						.style("fill", function(d) { return color(d.properties.gn_a1_code.substr(3)); })
				};

			showPopup = function(e, dpt) {
				var reponses = results[region].departements[dpt];
				var percent = Math.floor(percent_scale(reponses) * 100) / 100;

				popup = document.createElement("div");
				popup.className = "popup";

				if (reponses > 0) {
					popup.innerHTML =
						"<p class='dpt-nom'><span class='nom'>" + noms_departements[dpt] + "</span>" +
						"<span class='numero'>" + dpt + "</span></p>" +
						"<p class='dpt-resultats'>Inclus(s) dans <span class='reponses'>" + reponses + "</span> réponse" + (reponses > 1 ? "s" : "") +
						"<span class='pourcentage'> (" + percent + "%)</span></p>";
				} else {
					popup.innerHTML =
						"<p class='dpt-nom'><span class='nom'>" + noms_departements[dpt] + "</span>" +
						"<span class='numero'>" + dpt + "</span></p>" +
						"<p class='dpt-resultats'>Inclus(s) dans <span class='reponses'>aucune</span> réponse";
				}

				popup.style.top = (e.clientY + 10) + "px";
				popup.style.left = (e.clientX + 10) + "px";
				document.body.appendChild(popup);
			};
		});
});

</script>
</body>
