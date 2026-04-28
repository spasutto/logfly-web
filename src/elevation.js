
async function getElevation(lat, lon) {
  let r = await this.getElevations([lat, lon]);
  return Array.isArray(r) && r.length ? r[0] : -1;
}

async function getElevations(locations) {
  // Float64Array pour double float
  // attention au conflits d'endianness, getElevations.php décode en little endian
  let data = new Float32Array(locations);
  let r = await fetch('elevation/getElevation.php', { method: 'POST', body: data });
  let resptext = await r.text();
  let gndalt = [];

  try {
    for (let j=0; j<resptext.length; j+=2) {
      gndalt.push((j+2>resptext.length) ? 0 : (new DataView(new Uint8Array([resptext.charCodeAt(j), resptext.charCodeAt(j+1)]).buffer)).getInt16(0, true));
    }
  }
  catch (e) { console.log("error \"" + e + "\" while eval " + resptext); }

  //xhttp.overrideMimeType("text/plain; charset=x-user-defined");
  return gndalt;
}
