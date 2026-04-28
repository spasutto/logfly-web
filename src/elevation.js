
async function getElevation(lat, lon) {
  let r = await this.getElevations([lat, lon]);
  return Array.isArray(r) && r.length ? r[0] : -1;
}

async function getElevations(locations) {
  // Float64Array pour double float
  // attention au conflits d'endianness, getElevations.php décode en little endian
  let gndalt = [];
  let data = new Float32Array(locations);
  let r = await fetch('elevation/getElevation.php', { method: 'POST', body: data });

  try {
    let resp = await r.bytes();
    if (resp.length > 2) {
      resp = new DataView(resp.buffer);
      for (let j=0; j<resp.byteLength; j+=2) {
        gndalt.push(resp.getInt16(j, true));
      }
    }
  }
  catch (e) { console.error("error \"" + e + "\" while eval " + resp); }

  return gndalt;
}
