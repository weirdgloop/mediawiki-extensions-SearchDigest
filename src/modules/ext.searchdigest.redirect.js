/**
 * Additional script for SearchDigest to allow users to make quick redirects
 * @author Jayden Bailey
 */

const SDdata = require( './data.json' );

/**
 * Initialise the dialog window
 */
function SDRedirectDialog( config ) {
  SDRedirectDialog.super.call( this, config );
}
OO.inheritClass( SDRedirectDialog, OO.ui.ProcessDialog );

SDRedirectDialog.static.name = 'sdredir';
SDRedirectDialog.static.title = mw.message('searchdigest-redirect-title').text();
SDRedirectDialog.static.actions = [
  {
    flags: 'primary',
    label: mw.message('searchdigest-redirect-redirectbutton').text(),
    action: 'redirect'
  },
  {
    flags: 'safe',
    label: mw.message('cancel').text()
  }
];

SDRedirectDialog.prototype.initialize = function () {
  SDRedirectDialog.super.prototype.initialize.call( this );
  this.content = new OO.ui.PanelLayout( {
    padded: true,
    expanded: false
  } );
  this.pageToCreate = ''
  this.comboBox = new OO.ui.ComboBoxInputWidget( {
    value: '',
    options: [],
    $overlay: this.$overlay,
    placeholder: mw.message('searchdigest-redirect-inputplaceholder').text()
  } );
  this.comboBox.setValidation( 'non-empty' );
  this.content.$element.append( '<p>' + mw.message('searchdigest-redirect-helptext').text() + '</p>' );
  this.content.$element.append( this.comboBox.$element );

  this.comboBox.connect( this, { 'change': 'onComboboxInputChange' } );
  this.comboBox.connect( this, { 'enter': 'onComboboxInputEnter' } );

  this.$body.append( this.content.$element );
};

SDRedirectDialog.prototype.onComboboxInputChange = function ( value ) {
  var self = this;

  self.comboBox.getValidity().then(() => {
      this.actions.setAbilities({ redirect: true });
  }).catch(() => {
      this.actions.setAbilities({ redirect: false });
  })
  if (value.length) {
    // Input not empty, let's try search suggesting using the opensearch API
    api = new mw.Api();
    api.get( {
      action: 'opensearch',
      search: value,
      redirects: 'resolve',
      limit: 10,
      maxage: 900,
      smaxage: 900
    } ).done( function( data ) {
      let opts = [];
      if (data && !data.error && data[1].length) {
        let suggestions = data[1];
        for (i=0; i < suggestions.length; i++) {
          opts.push({ data: suggestions[i] })
        };
        self.comboBox.setOptions(opts);
      }
    });
  } else {
      self.comboBox.setOptions([]);
  }
};

SDRedirectDialog.prototype.onComboboxInputEnter = function () {
    var self = this;
    self.comboBox.getValidity().then(() => {
        this.executeAction('redirect');
    })
};

SDRedirectDialog.prototype.getSetupProcess = function ( data ) {
  data = data || {};
  return SDRedirectDialog.super.prototype.getSetupProcess.call( this, data )
      .next( function () {
        this.pageToCreate = data.page;
        this.$content.find('#sd-ptc').text(data.page);
        this.actions.setAbilities( {
          redirect: false
        } );
      }, this );
};

SDRedirectDialog.prototype.getReadyProcess = function ( data ) {
  return SDRedirectDialog.super.prototype.getReadyProcess.call( this, data )
      .next( function () {
        this.comboBox.focus();
      }, this );
}

SDRedirectDialog.prototype.getActionProcess = function ( action ) {
  var self = this;
  if ( action === 'redirect' ) {
    return new OO.ui.Process( function () {
      let value = this.comboBox.value;

      try {
        const possiblyAURL = new URL(value)
        const articlePath = mw.config.get('wgArticlePath').toString().replace('$1', '').replace(/[/\-\\^$*+?.()|[\]{}]/g, '\\$&');

        if (
            possiblyAURL.hostname === window.location.hostname
        ) {
          // Given value is a URL where the hostname is the same as the current hostname, so presumably they're
          // trying to use an external link to a wiki page on the current wiki. Parse it accordingly.
          const parsedArticle = (possiblyAURL.pathname + possiblyAURL.search + possiblyAURL.hash).match(new RegExp(articlePath + '(.*)'));
          if (parsedArticle.length) {
            value = parsedArticle[1];
          }
        }
      } catch (e) {}

      api = new mw.Api();

      // Check that the page we're redirecting to actually exists, as a final sanity check
      api.get({
          action: 'query',
          prop: 'info',
          titles: value,
          formatversion: 2
      }).done(function (data) {
          if (!data || !Object.values(data.query.pages).length) {
              return OO.ui.alert(mw.message('searchdigest-redirect-missing-target', self.pageToCreate).text());
          } else {
              let pageRes = Object.values(data.query.pages)[0];
              if (pageRes.missing || pageRes.invalid) {
                  return OO.ui.alert(mw.message('searchdigest-redirect-missing-target', self.pageToCreate).text());
              }

              // Create the page that we're redirecting
              return api.create(self.pageToCreate, {
                  summary: mw.format(SDdata.editsummary, value)
              }, SDdata.redirect + " [[" + value + "]]").done( function(data) {
                  mw.notify( mw.message('searchdigest-redirect-successtext', self.pageToCreate).text(), { tag: 'sd-created' } );
                  self.close( { page: self.pageToCreate } );
              }).fail( function() {
                  OO.ui.alert( mw.message('searchdigest-redirect-problem').text() );
              });
          }
      })
    }, this );
  }
  // Fallback to parent handler
  return SDRedirectDialog.super.prototype.getActionProcess.call( this, action );
};

SDRedirectDialog.prototype.getTeardownProcess = function ( data ) {
  var self = this;
  return SDRedirectDialog.super.prototype.getTeardownProcess.call( this, data )
      .next( function () {
        if (data && data.page) {
          // data.page is the page we created a redirect for
          // get the span element for the button of the page we just made
          let btnSpan = $(`*[data-page="${data.page}"]`);
          // get the a element which is a sibling of the span and then remove the link
          let pageLink = btnSpan.siblings('a');
          // remove the link (while keeping the text) and then strike through
          pageLink.contents().unwrap().wrap('<s></s>');
        };

        self.comboBox.setValue('');
        self.comboBox.setOptions([]);
        if (self.comboBox.getMenu().isVisible()) {
          // because of weird clipping bugs
          self.comboBox.getMenu().toggle();
        };
      }, this );
}

var redirDialog = new SDRedirectDialog( {
  size: 'medium'
} );

var windowManager = new OO.ui.WindowManager();
$( document.body ).append( windowManager.$element );
windowManager.addWindows( [ redirDialog ] );

/**
 * Add buttons
 */

var $redirBtns = $('a.sd-cr-btn');

$redirBtns.each(function (i) {
  $(this).on('click', function () {
    windowManager.openWindow( redirDialog, { page: $(this).attr('data-page') } )
  })
});
