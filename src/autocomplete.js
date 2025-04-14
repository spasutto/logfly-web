/*
  <input type="text" id="acbtest1">
  let acb1 = new AutoComplete(acbtest1, (t) => {
    return t?.split('');
  });
  let acb2 = new AutoComplete(acbtest2, 'search.php', {minlength:0});
*/
const AC_DEFAULT_OPTIONS = {
  minlength: 3,
  maxresults: 50,
  searchterm: 's',
  trim: true
};

class AutoComplete {
  get curText() {
    let t = this.input?.value ?? '';
    return this.options.trim?t.trim():t;
  }
  constructor(elem, searchaction, options) {
    this.loaded = false;
    this.results = [];
    this.options = {...AC_DEFAULT_OPTIONS, ...options};
    if (elem instanceof HTMLElement) {
      this.input = elem;
    } else if (typeof elem === 'string') {
      this.input = document.getElementById(elem);
    }
    if (typeof searchaction === 'string') {
      this.searchurl = searchaction;
    } else if (typeof searchaction === 'function') {
      this.predicate = searchaction;
    } else {
      throw new Error('invalid search predicate/url');
    }
    if (!(this.input instanceof HTMLElement)) {
      throw new Error('input element not provided');
    }
    if (this.input.type!='text') {
      throw new Error('invalid input element provided');
    }
    const stylename = 'ACB_AutoComplete_Style';
    if (!document.getElementById(stylename)) {
      let style = document.createElement('style');
      style.type = 'text/css';
      style.id=stylename;
      style.innerHTML = 'a.ACB { display:inline-block; width: 100%; font-weight:normal; color:black;text-decoration: none; } a.ACB:hover{background-color: #008fef; color: #d3ff22; } a.ACB.inactive { font-weight: normal; cursor: default;}';
      document.getElementsByTagName('head')[0].appendChild(style);
    }
    this.ul = document.createElement("ul");
    let iptrect = this.input.getBoundingClientRect();
    this.ul.style.cssText = 'display:none;float:left;position:absolute;list-style-type:none;background-color:white;border:solid 1px black;min-width:150px;padding:0px;margin:0px;z-index:2;max-height:200px;overflow-y:auto;left:'+iptrect.left+'px;';
    this.input.parentNode.insertBefore(this.ul, this.input.nextSibling);
    const inputHandler = function(e) {
      window.clearTimeout(this.sto);
      this.results = [{'text':'Chargement...', 'active':false}];
      this.createItems();
      this.sto = window.setTimeout(()=> {
        console.log('searching...');
        this.loaded = false;
        this.search();
      }, 250);
    }.bind(this);
    //this.input.addEventListener('change', inputHandler);
    this.input.addEventListener('input', inputHandler);
    this.input.addEventListener('propertychange', inputHandler); // for IE8
    this.input.addEventListener("focusout", (evt) => {
      if (!this.ul.contains(evt.relatedTarget)) {
        this.ul.style.display='none';
      }
    });
    this.input.addEventListener("focusin", (evt) => {
      if (!this.loaded && this.options.minlength <= this.curText.length) {
        this.search();
      } else if (this.results.length > 0) {
        this.ul.style.display='block';
      }
    });
    new ResizeObserver(this.iptSizeChanged.bind(this)).observe(this.input);
  }
  iptSizeChanged() {
    this.ul.style.minWidth = this.input.offsetWidth+'px';
    let iptrect = this.input.getBoundingClientRect();
    this.ul.style.left = iptrect.left+'px';
  }
  async search() {
    this.results = [{'text':'Chargement...', 'active':false}];
    this.createItems();
    this.ul.style.display='block';
    if (this.curText.length >= this.options.minlength) {
      // TODO : délai avant de lancer la recherche, le temps de finir de taper
      if (this.predicate) {
        this.results = await this.predicate(this.curText);
      } else if (this.searchurl) {
        let url = new URL(this.searchurl, window.location.href);
        url.searchParams.append(this.options.searchterm, this.curText);
        const reponse = await fetch(url);
        this.results = await reponse.json();
      }
      if (!this.results) {
        this.results = [];
      } else if (!Array.isArray(this.results)) {
        this.results = [this.results];
      } else if (this.results.length > this.options.maxresults) {
        this.results = this.results.slice(0, this.options.maxresults);
        this.results.push({'text':'[...]', 'active':false});
      }
    } else if (this.options.minlength > 0) {
      this.results = [{'text':`Taper au moins ${this.options.minlength} caractères`, 'active':false}];
    } else {
      this.results = [];
    }
    this.createItems();
    this.loaded = true;
  }
  createItems() {
    this.ul.innerHTML = '';
    if (this.results.length <= 0) {
      if (this.curText && this.options.minlength <= this.curText.length) {
        this.results = [{'text':'Aucun résultat', 'active':false}];
      } else {
        this.ul.style.display='none';
        return;
      }
    }
    this.results.forEach(res => {
      let itemtext = res.toString();
      if (typeof res.text === 'string') {
        itemtext = res.text;
      }
      let li = document.createElement("li");
      let a = document.createElement("a");
      a.classList.add("ACB");
      a.setAttribute('href', '#');
      if (typeof res.active === 'boolean' && !res.active) {
        a.classList.add('inactive');
      } else {
        const regsame = new RegExp("("+this.curText.replaceAll('(', '\\(').replaceAll(')', '\\)')+")", "gi");
        itemtext = itemtext.replaceAll(regsame, '<b>$1</b>');
      }
      a.innerHTML = itemtext;
      a.onclick = (() => {
        if (typeof res.active !== 'boolean' || res.active) {
          this.input.value = res.toString();
        }
        this.ul.style.display='none';
        this.loaded = false;
        return false;
      }).bind(this);
      li.appendChild(a);
      this.ul.appendChild(li);
    });
  }
}
