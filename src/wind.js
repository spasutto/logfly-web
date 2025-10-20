// https://gist.github.com/theKAKAN/b40bf54144a6eb90313ad00681e3fbcc
function getDirection(angle) {
	let directions = ["N","NNE","NE","ENE","E",
		"ESE", "SE", "SSE","S",
		"SSO","SO","OSO","O",
		"ONO","NO","NNO" ];
	let section = parseInt( angle/22.5 + 0.5 );
	return directions[section % 16];
}
function formatWind(wind) {
  let data = `<style>
  .tablebalises td {
    border-color: #b5b5b5;
    border-width: 0 0 1px 1px;
    border-style: solid;
  }
  .tablebalises span {
    display: inline-block;
  }
  </style>
  <table class="tablebalises">`;
  data += wind.map(w => `<tr><td><a href="https://maps.google.com/?q=${w.lat},${w.lon}" target="_Blank" title="afficher l'emplacement sur google maps">${w.nom}</a> <small>(${w.distance} km)</small></td><td><span style="transform: rotate(${w.vent.dir+180}deg);float: left" title="${w.vent.dir}°${getDirection(w.vent.dir)} direction instantanée">&#8679;</span> ${w.vent.min}&nbsp;/&nbsp;${w.vent.moy}<span style="transform: rotate(${w.vent.dirm+180}deg)" title="${w.vent.dirm}°${getDirection(w.vent.dirm)} direction moyenne">&#8679;</span>&nbsp;/&nbsp;${w.vent.max}</td></tr>`).join('');
  data += '</table>';
  return data;
}
async function getWind(id) {
  let data = null;
  try {
    const r = await fetch(`Tracklogs/w${id}.json`);
    if (!r.ok) return null;
    data = await r.json();
  } catch (e) {
    console.error(e);
  }
  if (data == null) return;
  return data;
}
async function updateWind(id, lat, lon, ts, silent = false) {
  ts = ts ?? Math.trunc(new Date().getTime()/1000);
  let data = null;
  try {
    const r = await fetch(`wind.php?lat=${lat}&lon=${lon}&ts=${ts}`);
    data = await r.json();
  } catch (e2) {
    console.error(e);
  }
  if (data?.constructor !== Array) return;
  if (!silent && !confirm(`${data.length} balises trouvées, mettre à jour?`)) return data;
  try {
    fetch("wind.php?wind&id="+id, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(data)
    });
  } catch(e) {
    console.error(e);
  }
  return data;
}

