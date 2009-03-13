var RedTree;
if (! RedTree) RedTree = {};

RedTree.Util = {
  createIframe: function(el) {
    var doc = el.ownerDocument;
    var iframe = el.appendChild(doc.createElement("iframe"));
    iframe.doc = null;

    if(iframe.contentDocument) // Firefox, Opera
       iframe.doc = iframe.contentDocument;
    else if(iframe.contentWindow) // Internet Explorer
       iframe.doc = iframe.contentWindow.document;
    else if(iframe.document) // Others?
       iframe.doc = iframe.document;

    if(iframe.doc == null)
       throw new Erorr('couldn\'t determine iframe document');

    return iframe;
  },

  getLink: function (rel, type, title)
  {
    var head = document.getElementsByTagName('head')[0];
    var links = head.getElementsByTagName('link');
    var r = {};
    for (var i=0; i<links.length; ++i) {
      var link = links[i];
      if (link.rel == rel && link.type == type) {
        if (link.title) {
          r[link.title] = link.href;
        } else {
          r['_default'] = link.href;
        }
      }
    }
    if (title == undefined) {
      return r;
    } else {
      if (r[title] == undefined) {
        return null;
      } else {
        return r[title];
      }
    }
    return r;
  },

  // Method binding function
  bind: function (s, f)
  {
    return function() {
      return f.apply(s, arguments);
    }
  },

  unlisten: function (ob, ev, cb)
  {
    if (ob.removeEventListener) {
      ob.removeEventListener(ev, cb, false);
    } else {
      ev = 'on'+ev[0].toUpperCase()+ev.substr(1);
      if (ob.detachEvent) {
        ob.detachEvent(ev, cb);
      } else {
        throw new Error("Can't unlisten");
      }
    }
  },

  listen: function (ob, ev, cb)
  {
    if (ob.addEventListener) {
      ob.addEventListener(ev, cb, false);
    } else {
      ev = 'on'+ev[0].toUpperCase()+ev.substr(1);
      if (ob.attachEvent) {
        ob.attachEvent(ev, cb);
      } else {
        throw new Error("Can't listen");
      }
    }
    return cb;
  },

  /**
   * Registers a page load callback.
   *
   * @param f the function to call
   */
  addLoadEvent: function (f)
  {
    RedTree.Util.listen(window, 'load', f);
  },

  /**
   * Delays the instantion of a class, returns a factory subclass that creates an
   * instance of the class with the given arguments when instantiated.
   *
   * @param cls the class to create an instance of
   * @param cargs arguments to pass to class's constructor
   *
   * Example:
   *   // Nothing is actually created here, just a promise to create a MumbleFoo in a certain way
   *   factory = delayedInstantiation(MumbleFoo, ['alpha', 'bravo']);
   *
   *   // some time passes
   *   o = new factory(); // as if this line read: o = new MumbleFoo('alpha', 'bravo');
   */
  delayedInstantiation: function (cls, cargs)
  {
    function factory() {
      cls.apply(this, cargs);
    }
    factory.prototype = cls.prototype;

    return factory;
  },

  /*
   * Simple mixing utility, mixes properties of ecah succesive argument into base,
   * then returns base for convenience.
   *
   * Example:
   *   o = mix({}, {a: 2}, {b: 3}, {a: 4});
   *   o == {
   *     a: 4,
   *     b: 3
   *   };
   *
   *   // NOTE: modified by reference even if you don't assign the return value
   *   mix(o, {c: 5});
   *   o == {
   *     a: 4,
   *     b: 3,
   *     c: 5
   *   };
   */
  mix: function (base)
  {
    var a=arguments, i, o, n;
    for (i=1; i<a.length; ++i) {
      o=a[i];
      for (n in o)
        base[n] = o[n];
    }
    return base;
  },

  stopEvent: function (e) {
    if (typeof e == 'undefined') e = window.event;
    if (typeof e == 'undefined') return;
    if (typeof e.stopPropagation == 'function') {
      e.stopPropagation();
      e.preventDefault();
    } else {
      e.cancelBubble = true;
      e.returnValue  = false;
    }
    return false;
  }
};
