// Retrieved from http://www.kelvinluck.com/assets/jquery/datePicker/v2/demo/index.html
(function($){$.fn.extend({renderCalendar:function(s)
{var dc=function(a)
{return document.createElement(a);};s=$.extend({},$.fn.datePicker.defaults,s);if(s.showHeader!=$.dpConst.SHOW_HEADER_NONE){var headRow=$(dc('tr'));for(var i=Date.firstDayOfWeek;i<Date.firstDayOfWeek+7;i++){var weekday=i%7;var day=Date.dayNames[weekday];headRow.append(jQuery(dc('th')).attr({'scope':'col','abbr':day,'title':day,'class':(weekday==0||weekday==6?'weekend':'weekday')}).html(s.showHeader==$.dpConst.SHOW_HEADER_SHORT?day.substr(0,1):day));}};var calendarTable=$(dc('table')).attr({'cellspacing':2}).addClass('jCalendar').append((s.showHeader!=$.dpConst.SHOW_HEADER_NONE?$(dc('thead')).append(headRow):dc('thead')));var tbody=$(dc('tbody'));var today=(new Date()).zeroTime();today.setHours(12);var month=s.month==undefined?today.getMonth():s.month;var year=s.year||today.getFullYear();var currentDate=(new Date(year,month,1,12,0,0));var firstDayOffset=Date.firstDayOfWeek-currentDate.getDay()+1;if(firstDayOffset>1)firstDayOffset-=7;var weeksToDraw=Math.ceil(((-1*firstDayOffset+1)+currentDate.getDaysInMonth())/7);currentDate.addDays(firstDayOffset-1);var doHover=function(firstDayInBounds)
{return function()
{if(s.hoverClass){var $this=$(this);if(!s.selectWeek){$this.addClass(s.hoverClass);}else if(firstDayInBounds&&!$this.is('.disabled')){$this.parent().addClass('activeWeekHover');}}}};var unHover=function()
{if(s.hoverClass){var $this=$(this);$this.removeClass(s.hoverClass);$this.parent().removeClass('activeWeekHover');}};var w=0;while(w++<weeksToDraw){var r=jQuery(dc('tr'));var firstDayInBounds=s.dpController?currentDate>s.dpController.startDate:false;for(var i=0;i<7;i++){var thisMonth=currentDate.getMonth()==month;var d=$(dc('td')).text(currentDate.getDate()+'').addClass((thisMonth?'current-month ':'other-month ')+
(currentDate.isWeekend()?'weekend ':'weekday ')+
(thisMonth&&currentDate.getTime()==today.getTime()?'today ':'')).data('datePickerDate',currentDate.asString()).hover(doHover(firstDayInBounds),unHover);r.append(d);if(s.renderCallback){s.renderCallback(d,currentDate,month,year);}
currentDate=new Date(currentDate.getFullYear(),currentDate.getMonth(),currentDate.getDate()+1,12,0,0);}
tbody.append(r);}
calendarTable.append(tbody);return this.each(function()
{$(this).empty().append(calendarTable);});},datePicker:function(s)
{if(!$.event._dpCache)$.event._dpCache=[];s=$.extend({},$.fn.datePicker.defaults,s);return this.each(function()
{var $this=$(this);var alreadyExists=true;if(!this._dpId){this._dpId=$.event.guid++;$.event._dpCache[this._dpId]=new DatePicker(this);alreadyExists=false;}
if(s.inline){s.createButton=false;s.displayClose=false;s.closeOnSelect=false;$this.empty();}
var controller=$.event._dpCache[this._dpId];controller.init(s);if(!alreadyExists&&s.createButton){controller.button=$('<a href="#" class="dp-choose-date" title="'+$.dpText.TEXT_CHOOSE_DATE+'">'+$.dpText.TEXT_CHOOSE_DATE+'</a>').bind('click',function()
{$this.dpDisplay(this);this.blur();return false;});$this.after(controller.button);}
if(!alreadyExists&&$this.is(':text')){$this.bind('dateSelected',function(e,selectedDate,$td)
{this.value=selectedDate.asString();}).bind('change',function()
{if(this.value==''){controller.clearSelected();}else{var d=Date.fromString(this.value);if(d){controller.setSelected(d,true,true);}}});if(s.clickInput){$this.bind('click',function()
{$this.trigger('change');$this.dpDisplay();});}
var d=Date.fromString(this.value);if(this.value!=''&&d){controller.setSelected(d,true,true);}}
$this.addClass('dp-applied');})},dpSetDisabled:function(s)
{return _w.call(this,'setDisabled',s);},dpSetStartDate:function(d)
{return _w.call(this,'setStartDate',d);},dpSetEndDate:function(d)
{return _w.call(this,'setEndDate',d);},dpGetSelected:function()
{var c=_getController(this[0]);if(c){return c.getSelected();}
return null;},dpSetSelected:function(d,v,m,e)
{if(v==undefined)v=true;if(m==undefined)m=true;if(e==undefined)e=true;return _w.call(this,'setSelected',Date.fromString(d),v,m,e);},dpSetDisplayedMonth:function(m,y)
{return _w.call(this,'setDisplayedMonth',Number(m),Number(y),true);},dpDisplay:function(e)
{return _w.call(this,'display',e);},dpSetRenderCallback:function(a)
{return _w.call(this,'setRenderCallback',a);},dpSetPosition:function(v,h)
{return _w.call(this,'setPosition',v,h);},dpSetOffset:function(v,h)
{return _w.call(this,'setOffset',v,h);},dpClose:function()
{return _w.call(this,'_closeCalendar',false,this[0]);},dpRerenderCalendar:function()
{return _w.call(this,'_rerenderCalendar');},_dpDestroy:function()
{}});var _w=function(f,a1,a2,a3,a4)
{return this.each(function()
{var c=_getController(this);if(c){c[f](a1,a2,a3,a4);}});};function DatePicker(ele)
{this.ele=ele;this.displayedMonth=null;this.displayedYear=null;this.startDate=null;this.endDate=null;this.showYearNavigation=null;this.closeOnSelect=null;this.displayClose=null;this.rememberViewedMonth=null;this.selectMultiple=null;this.numSelectable=null;this.numSelected=null;this.verticalPosition=null;this.horizontalPosition=null;this.verticalOffset=null;this.horizontalOffset=null;this.button=null;this.renderCallback=[];this.selectedDates={};this.inline=null;this.context='#dp-popup';this.settings={};};$.extend(DatePicker.prototype,{init:function(s)
{this.setStartDate(s.startDate);this.setEndDate(s.endDate);this.setDisplayedMonth(Number(s.month),Number(s.year));this.setRenderCallback(s.renderCallback);this.showYearNavigation=s.showYearNavigation;this.closeOnSelect=s.closeOnSelect;this.displayClose=s.displayClose;this.rememberViewedMonth=s.rememberViewedMonth;this.selectMultiple=s.selectMultiple;this.numSelectable=s.selectMultiple?s.numSelectable:1;this.numSelected=0;this.verticalPosition=s.verticalPosition;this.horizontalPosition=s.horizontalPosition;this.hoverClass=s.hoverClass;this.setOffset(s.verticalOffset,s.horizontalOffset);this.inline=s.inline;this.settings=s;if(this.inline){this.context=this.ele;this.display();}},setStartDate:function(d)
{if(d){this.startDate=Date.fromString(d);}
if(!this.startDate){this.startDate=(new Date()).zeroTime();}
this.setDisplayedMonth(this.displayedMonth,this.displayedYear);},setEndDate:function(d)
{if(d){this.endDate=Date.fromString(d);}
if(!this.endDate){this.endDate=(new Date('12/31/2999'));}
if(this.endDate.getTime()<this.startDate.getTime()){this.endDate=this.startDate;}
this.setDisplayedMonth(this.displayedMonth,this.displayedYear);},setPosition:function(v,h)
{this.verticalPosition=v;this.horizontalPosition=h;},setOffset:function(v,h)
{this.verticalOffset=parseInt(v)||0;this.horizontalOffset=parseInt(h)||0;},setDisabled:function(s)
{$e=$(this.ele);$e[s?'addClass':'removeClass']('dp-disabled');if(this.button){$but=$(this.button);$but[s?'addClass':'removeClass']('dp-disabled');$but.attr('title',s?'':$.dpText.TEXT_CHOOSE_DATE);}
if($e.is(':text')){$e.attr('disabled',s?'disabled':'');}},setDisplayedMonth:function(m,y,rerender)
{if(this.startDate==undefined||this.endDate==undefined){return;}
var s=new Date(this.startDate.getTime());s.setDate(1);var e=new Date(this.endDate.getTime());e.setDate(1);var t;if((!m&&!y)||(isNaN(m)&&isNaN(y))){t=new Date().zeroTime();t.setDate(1);}else if(isNaN(m)){t=new Date(y,this.displayedMonth,1);}else if(isNaN(y)){t=new Date(this.displayedYear,m,1);}else{t=new Date(y,m,1)}
if(t.getTime()<s.getTime()){t=s;}else if(t.getTime()>e.getTime()){t=e;}
var oldMonth=this.displayedMonth;var oldYear=this.displayedYear;this.displayedMonth=t.getMonth();this.displayedYear=t.getFullYear();if(rerender&&(this.displayedMonth!=oldMonth||this.displayedYear!=oldYear))
{this._rerenderCalendar();$(this.ele).trigger('dpMonthChanged',[this.displayedMonth,this.displayedYear]);}},setSelected:function(d,v,moveToMonth,dispatchEvents)
{if(d<this.startDate||d.zeroTime()>this.endDate.zeroTime()){return;}
var s=this.settings;if(s.selectWeek)
{d=d.addDays(-(d.getDay()-Date.firstDayOfWeek+7)%7);if(d<this.startDate)
{return;}}
if(v==this.isSelected(d))
{return;}
if(this.selectMultiple==false){this.clearSelected();}else if(v&&this.numSelected==this.numSelectable){return;}
if(moveToMonth&&(this.displayedMonth!=d.getMonth()||this.displayedYear!=d.getFullYear())){this.setDisplayedMonth(d.getMonth(),d.getFullYear(),true);}
this.selectedDates[d.asString()]=v;this.numSelected+=v?1:-1;var selectorString='td.'+(d.getMonth()==this.displayedMonth?'current-month':'other-month');var $td;$(selectorString,this.context).each(function()
{if($(this).data('datePickerDate')==d.asString()){$td=$(this);if(s.selectWeek)
{$td.parent()[v?'addClass':'removeClass']('selectedWeek');}
$td[v?'addClass':'removeClass']('selected');}});$('td',this.context).not('.selected')[this.selectMultiple&&this.numSelected==this.numSelectable?'addClass':'removeClass']('unselectable');if(dispatchEvents)
{var s=this.isSelected(d);$e=$(this.ele);var dClone=Date.fromString(d.asString());$e.trigger('dateSelected',[dClone,$td,s]);$e.trigger('change');}},isSelected:function(d)
{return this.selectedDates[d.asString()];},getSelected:function()
{var r=[];for(var s in this.selectedDates){if(this.selectedDates[s]==true){r.push(Date.fromString(s));}}
return r;},clearSelected:function()
{this.selectedDates={};this.numSelected=0;$('td.selected',this.context).removeClass('selected').parent().removeClass('selectedWeek');},display:function(eleAlignTo)
{if($(this.ele).is('.dp-disabled'))return;eleAlignTo=eleAlignTo||this.ele;var c=this;var $ele=$(eleAlignTo);var eleOffset=$ele.offset();var $createIn;var attrs;var attrsCalendarHolder;var cssRules;if(c.inline){$createIn=$(this.ele);attrs={'id':'calendar-'+this.ele._dpId,'class':'dp-popup dp-popup-inline'};$('.dp-popup',$createIn).remove();cssRules={};}else{$createIn=$('body');attrs={'id':'dp-popup','class':'dp-popup'};cssRules={'top':eleOffset.top+c.verticalOffset,'left':eleOffset.left+c.horizontalOffset};var _checkMouse=function(e)
{var el=e.target;var cal=$('#dp-popup')[0];while(true){if(el==cal){return true;}else if(el==document){c._closeCalendar();return false;}else{el=$(el).parent()[0];}}};this._checkMouse=_checkMouse;c._closeCalendar(true);$(document).bind('keydown.datepicker',function(event)
{if(event.keyCode==27){c._closeCalendar();}});}
if(!c.rememberViewedMonth)
{var selectedDate=this.getSelected()[0];if(selectedDate){selectedDate=new Date(selectedDate);this.setDisplayedMonth(selectedDate.getMonth(),selectedDate.getFullYear(),false);}}
$createIn.append($('<div></div>').attr(attrs).css(cssRules).append($('<h2></h2>'),$('<div class="dp-nav-prev"></div>').append($('<a class="dp-nav-prev-year" href="#" title="'+$.dpText.TEXT_PREV_YEAR+'"><<</a>').bind('click',function()
{return c._displayNewMonth.call(c,this,0,-1);}),$('<a class="dp-nav-prev-month" href="#" title="'+$.dpText.TEXT_PREV_MONTH+'"><</a>').bind('click',function()
{return c._displayNewMonth.call(c,this,-1,0);})),$('<div class="dp-nav-next"></div>').append($('<a class="dp-nav-next-year" href="#" title="'+$.dpText.TEXT_NEXT_YEAR+'">>></a>').bind('click',function()
{return c._displayNewMonth.call(c,this,0,1);}),$('<a class="dp-nav-next-month" href="#" title="'+$.dpText.TEXT_NEXT_MONTH+'">></a>').bind('click',function()
{return c._displayNewMonth.call(c,this,1,0);})),$('<div class="dp-calendar"></div>')).bgIframe());var $pop=this.inline?$('.dp-popup',this.context):$('#dp-popup');if(this.showYearNavigation==false){$('.dp-nav-prev-year, .dp-nav-next-year',c.context).css('display','none');}
if(this.displayClose){$pop.append($('<a href="#" id="dp-close">'+$.dpText.TEXT_CLOSE+'</a>').bind('click',function()
{c._closeCalendar();return false;}));}
c._renderCalendar();$(this.ele).trigger('dpDisplayed',$pop);if(!c.inline){if(this.verticalPosition==$.dpConst.POS_BOTTOM){$pop.css('top',eleOffset.top+$ele.height()-$pop.height()+c.verticalOffset);}
if(this.horizontalPosition==$.dpConst.POS_RIGHT){$pop.css('left',eleOffset.left+$ele.width()-$pop.width()+c.horizontalOffset);}
$(document).bind('mousedown.datepicker',this._checkMouse);}},setRenderCallback:function(a)
{if(a==null)return;if(a&&typeof(a)=='function'){a=[a];}
this.renderCallback=this.renderCallback.concat(a);},cellRender:function($td,thisDate,month,year){var c=this.dpController;var d=new Date(thisDate.getTime());$td.bind('click',function()
{var $this=$(this);if(!$this.is('.disabled')){c.setSelected(d,!$this.is('.selected')||!c.selectMultiple,false,true);if(c.closeOnSelect){if(c.settings.autoFocusNextInput){var ele=c.ele;var found=false;$(':input',ele.form).each(function()
{if(found){$(this).focus();return false;}
if(this==ele){found=true;}});}else{c.ele.focus();}
c._closeCalendar();}}});if(c.isSelected(d)){$td.addClass('selected');if(c.settings.selectWeek)
{$td.parent().addClass('selectedWeek');}}else if(c.selectMultiple&&c.numSelected==c.numSelectable){$td.addClass('unselectable');}},_applyRenderCallbacks:function()
{var c=this;$('td',this.context).each(function()
{for(var i=0;i<c.renderCallback.length;i++){$td=$(this);c.renderCallback[i].apply(this,[$td,Date.fromString($td.data('datePickerDate')),c.displayedMonth,c.displayedYear]);}});return;},_displayNewMonth:function(ele,m,y)
{if(!$(ele).is('.disabled')){this.setDisplayedMonth(this.displayedMonth+m,this.displayedYear+y,true);}
ele.blur();return false;},_rerenderCalendar:function()
{this._clearCalendar();this._renderCalendar();},_renderCalendar:function()
{$('h2',this.context).html((new Date(this.displayedYear,this.displayedMonth,1)).asString($.dpText.HEADER_FORMAT));$('.dp-calendar',this.context).renderCalendar($.extend({},this.settings,{month:this.displayedMonth,year:this.displayedYear,renderCallback:this.cellRender,dpController:this,hoverClass:this.hoverClass}));if(this.displayedYear==this.startDate.getFullYear()&&this.displayedMonth==this.startDate.getMonth()){$('.dp-nav-prev-year',this.context).addClass('disabled');$('.dp-nav-prev-month',this.context).addClass('disabled');$('.dp-calendar td.other-month',this.context).each(function()
{var $this=$(this);if(Number($this.text())>20){$this.addClass('disabled');}});var d=this.startDate.getDate();$('.dp-calendar td.current-month',this.context).each(function()
{var $this=$(this);if(Number($this.text())<d){$this.addClass('disabled');}});}else{$('.dp-nav-prev-year',this.context).removeClass('disabled');$('.dp-nav-prev-month',this.context).removeClass('disabled');var d=this.startDate.getDate();if(d>20){var st=this.startDate.getTime();var sd=new Date(st);sd.addMonths(1);if(this.displayedYear==sd.getFullYear()&&this.displayedMonth==sd.getMonth()){$('.dp-calendar td.other-month',this.context).each(function()
{var $this=$(this);if(Date.fromString($this.data('datePickerDate')).getTime()<st){$this.addClass('disabled');}});}}}
if(this.displayedYear==this.endDate.getFullYear()&&this.displayedMonth==this.endDate.getMonth()){$('.dp-nav-next-year',this.context).addClass('disabled');$('.dp-nav-next-month',this.context).addClass('disabled');$('.dp-calendar td.other-month',this.context).each(function()
{var $this=$(this);if(Number($this.text())<14){$this.addClass('disabled');}});var d=this.endDate.getDate();$('.dp-calendar td.current-month',this.context).each(function()
{var $this=$(this);if(Number($this.text())>d){$this.addClass('disabled');}});}else{$('.dp-nav-next-year',this.context).removeClass('disabled');$('.dp-nav-next-month',this.context).removeClass('disabled');var d=this.endDate.getDate();if(d<13){var ed=new Date(this.endDate.getTime());ed.addMonths(-1);if(this.displayedYear==ed.getFullYear()&&this.displayedMonth==ed.getMonth()){$('.dp-calendar td.other-month',this.context).each(function()
{var $this=$(this);var cellDay=Number($this.text());if(cellDay<13&&cellDay>d){$this.addClass('disabled');}});}}}
this._applyRenderCallbacks();},_closeCalendar:function(programatic,ele)
{if(!ele||ele==this.ele)
{$(document).unbind('mousedown.datepicker');$(document).unbind('keydown.datepicker');this._clearCalendar();$('#dp-popup a').unbind();$('#dp-popup').empty().remove();if(!programatic){$(this.ele).trigger('dpClosed',[this.getSelected()]);}}},_clearCalendar:function()
{$('.dp-calendar td',this.context).unbind();$('.dp-calendar',this.context).empty();}});$.dpConst={SHOW_HEADER_NONE:0,SHOW_HEADER_SHORT:1,SHOW_HEADER_LONG:2,POS_TOP:0,POS_BOTTOM:1,POS_LEFT:0,POS_RIGHT:1,DP_INTERNAL_FOCUS:'dpInternalFocusTrigger'};$.dpText={TEXT_PREV_YEAR:'Previous year',TEXT_PREV_MONTH:'Previous month',TEXT_NEXT_YEAR:'Next year',TEXT_NEXT_MONTH:'Next month',TEXT_CLOSE:'Close',TEXT_CHOOSE_DATE:'Choose date',HEADER_FORMAT:'mmmm yyyy'};$.dpVersion='$Id: jquery.datePicker.js 100 2010-06-23 10:45:28Z kelvin.luck $';$.fn.datePicker.defaults={month:undefined,year:undefined,showHeader:$.dpConst.SHOW_HEADER_SHORT,startDate:undefined,endDate:undefined,inline:false,renderCallback:null,createButton:true,showYearNavigation:true,closeOnSelect:true,displayClose:false,selectMultiple:false,numSelectable:Number.MAX_VALUE,clickInput:false,rememberViewedMonth:true,selectWeek:false,verticalPosition:$.dpConst.POS_TOP,horizontalPosition:$.dpConst.POS_LEFT,verticalOffset:0,horizontalOffset:0,hoverClass:'dp-hover',autoFocusNextInput:false};function _getController(ele)
{if(ele._dpId)return $.event._dpCache[ele._dpId];return false;};if($.fn.bgIframe==undefined){$.fn.bgIframe=function(){return this;};};$(window).bind('unload',function(){var els=$.event._dpCache||[];for(var i in els){$(els[i].ele)._dpDestroy();}});})(jQuery);
