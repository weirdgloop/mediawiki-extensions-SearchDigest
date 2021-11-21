/**
 * Additional script for SearchDigest to allow users to make quick redirects
 * @author Jayden Bailey
 */

/**
 * Initialise the dialog window
 */
function SDRedirectDialog( config ) {
	SDRedirectDialog.super.call( this, config );
}
OO.inheritClass( SDRedirectDialog, OO.ui.ProcessDialog ); 

SDRedirectDialog.static.name = 'sdredir';
SDRedirectDialog.static.title = mw.message('searchdigest-redirect-title').escaped();
SDRedirectDialog.static.actions = [
	{ 
		flags: 'primary', 
		label: mw.message('searchdigest-redirect-redirectbutton').escaped(), 
		action: 'redirect' 
	},
	{ 
		flags: 'safe', 
		label: mw.message('cancel').escaped() 
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
    $overlay: true,
    placeholder: mw.message('searchdigest-redirect-inputplaceholder').escaped()
  } );
  this.content.$element.append( '<p>' + mw.message('searchdigest-redirect-helptext').text() + '</p>' );
  this.content.$element.append( this.comboBox.$element );

  this.comboBox.connect( this, { 'change': 'onComboboxInputChange' } );
  this.comboBox.connect( this, { 'enter': 'onComboboxInputEnter' } );

  this.$body.append( this.content.$element );
};

SDRedirectDialog.prototype.onComboboxInputChange = function ( value ) {
  this.actions.setAbilities( {
    redirect: !!value.length
  } );
  var self = this;
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
  };
};

SDRedirectDialog.prototype.onComboboxInputEnter = function () {
  this.executeAction('redirect');
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
      api = new mw.Api();
      api.create( this.pageToCreate, {
        summary: mw.message('searchdigest-redirect-editsummary', this.comboBox.value).escaped()
      }, "#REDIRECT [[" + this.comboBox.value + "]]").done( function(data) {
        mw.notify( mw.message('searchdigest-redirect-successtext', self.pageToCreate).escaped(), { tag: 'sd-created' } );
        self.close( { page: self.pageToCreate } );
      }).fail( function(data) {
        OO.ui.alert( mw.message('searchdigest-redirect-problem').escaped() );
        self.close( { page: self.pageToCreate } );
      });
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
      // finally, remove the button span
      btnSpan.remove();
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

var $redirBtns = $('span.sd-cr-btn');

$redirBtns.each(function (i) {
  // Create new OOUI button for each item
  var btn = new OO.ui.ButtonWidget( { 
    label: mw.message('searchdigest-redirect-buttontext').escaped(),
    classes: ['sd-cr-btn-wdgt']
  } );

  btn.on('click', function () {
    windowManager.openWindow( redirDialog, { page: btn.$element.parent().attr('data-page') } )
  })

  $(this).append(btn.$element);
});