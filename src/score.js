
var scoringrule = 'FFVL';

function score(igccontent, cbk) {
    IGCScore.score(igccontent, (score) => {
      if (score && typeof score.value == 'object') {
        score = score.value;
      }
      if (score && typeof score.opt == 'object' && typeof score.opt.flight == 'object') delete score.opt.flight;
      score.closingCircleRadius = 0;
      let rule = IGCScore.xcScoringRules[scoringrule].find(sr => sr.name == score.opt.scoring.name);
      if (typeof rule === 'object') {
        if (typeof rule.closingDistance !== 'function') {
          // on essaie de trouver la règle fermante qui propose le plus grand rayon
          let rules = IGCScore.xcScoringRules[scoringrule].filter(r => typeof r.closingDistance === 'function').sort((a,b) => b.closingDistance(score.scoreInfo.distance, {'scoring':b})-a.closingDistance(score.scoreInfo.distance, {'scoring':a}));
          if (rules.length <= 0) return;
          rule = rules[0];
        }
        score.closingCircleRadius = rule.closingDistance(score.scoreInfo.distance, {'scoring':rule}) * 1000;
      }
      cbk(score);
    }, scoringrule, 60);
}

/*


          IGCScore.score(this.responseText, (score) => {
            message("");
            if (score && typeof score.value == 'object') {
              score = score.value;
            }
            if (score && typeof score.opt == 'object' && typeof score.opt.flight == 'object') delete score.opt.flight;
            if (confirm ("Le score calculé est de " + Math.round(score.score*10)/10 + " points pour "+Math.round(score.scoreInfo.distance*10)/10+"km, mettre à jour?")) {
              message("enregistrement...");
              postFlightScore(id, score, (msg) => {message("");alert(msg == "OK"?"Fait!":"Il semble qu'il y'ai eu un problème : " + msg);});
            }
          }, 'FFVL', 60);
          
          */