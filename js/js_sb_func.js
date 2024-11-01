/*
FileName: js_sb_func.js
Description: jquery/javascript functions needed for spotbot to correctly work.
Version: 0.0.9
Author: Jake Helbig
Author URI: http://www.jakehelbig.com
License: GPLv3
    Version 3, 29 June 2007
    Copyright (C) 2007 Free Software Foundation, Inc. <http://fsf.org/>
    Everyone is permitted to copy and distribute verbatim copies of this license document, but changing it is not allowed.
*/

jQuery(function (SB) {
    
    
    
    
    /*
     *   TOP NAVIGATION CONTROLS
     */

    //RELOAD PAGE CONTORL
    SB('.refload').click(function () {
        window.location.reload();
    });
    
    
    //DELETE, BOT, HUMAN SELECT OPTION CONTROL
    SB('.selected_action').change(function (){
       action = SB('.selected_action option:selected').val();
       
       //got this from Keven B thanks buddy ^_^
       //http://forum.jquery.com/topic/get-list-of-values-of-all-selected-same-name-check-boxes-in-a-form#14737000003404536
       //http://jsfiddle.net/dY93b/
       SB.fn.valList = function(){
            return SB.map( this, function (elem) {
                  return elem.value || "";
            }).join( "," );
        };
       
       var list = (SB("input:checked").valList()); 
       
       
       //DELETE ALL SELECTED
       if(action == 'delall'){
            var ask = confirm("You are about to delete all selected items!");
            if(ask == true){
                SB('.refload').attr('src', '../wp-content/plugins/spotbot/images/loading.gif');
                SB.ajax({
                    type: "POST",
                    url: location.href,
                    data: 'id='+encodeURIComponent(list)+'&action=del',
                    success: function(){
                        SB('.refload').attr('src', '../wp-content/plugins/spotbot/images/refresh.png');
                        SB('.tablenav-pages').load(location.href + ' .tablenav-pages > *');
                        SB('.listbody').load(location.href + ' .listbody > *');
                    }
                });
            }
       }
       
       //SET ALL SELECTED TO ROBOTS
       if(action == 'botall'){
            var ask = confirm("You are about to set all selected items to Bots!");
            if(ask == true){
                SB('.refload').attr('src', '../wp-content/plugins/spotbot/images/loading.gif');
                SB.ajax({
                    type: "POST",
                    url: location.href,
                    data: 'id='+encodeURIComponent(list)+'&action=bot',
                    success: function(){
                        SB('.refload').attr('src', '../wp-content/plugins/spotbot/images/refresh.png');
                        SB('.tablenav-pages').load(location.href + ' .tablenav-pages > *');
                        SB('.listbody').load(location.href + ' .listbody > *');
                    }
                });
            }
       }
       
       //SET ALL SELECTED TO HUMANS
       if(action == 'humall'){
            var ask = confirm("You are about to set all selected items to Human!");
            if(ask == true){
                SB('.refload').attr('src', '../wp-content/plugins/spotbot/images/loading.gif');
                SB.ajax({
                    type: "POST",
                    url: location.href,
                    data: 'id='+encodeURIComponent(list)+'&action=hum',
                    success: function(){
                        SB('.refload').attr('src', '../wp-content/plugins/spotbot/images/refresh.png');
                        SB('.tablenav-pages').load(location.href + ' .tablenav-pages > *');
                        SB('.listbody').load(location.href + ' .listbody > *');
                    }
                });
            }
       }
    });
    
    //ITEMS LISTED PER PAGE CONTROL
    SB('.limit').change(function (){
       limit = SB('.limit option:selected').val();
       
       if(!(limit == "")){
            SB.cookie('spotbot_limit', limit);   
            //location.reload();
            SB('.tablenav-pages').load(location.href + ' .tablenav-pages > *');
            SB('.listbody').load(location.href + ' .listbody > *');
       }
       
    });
    
    
    //SEARCH BUTTON CONTROL
    SB('#searchbtn').click(function (){
        searchquery = SB('#searchquery').val();
        if(!(searchquery == "")){
            window.location = '/wp-admin/admin.php?page=sb_list&spotbot_search='+encodeURIComponent(searchquery);
        }else{
            window.location = '/wp-admin/admin.php?page=sb_list';
        }
    });
    
    //SEARCH INPUT CONTROL
    SB('#searchquery').keypress(function (event){
        if ( event.which == 13 ) {
            searchquery = SB('#searchquery').val();
            if(!(searchquery == "")){
                window.location = '/wp-admin/admin.php?page=sb_list&spotbot_search='+encodeURIComponent(searchquery);
            }else{
                window.location = '/wp-admin/admin.php?page=sb_list';
            }
        }
    });
    
    
    
    
    /*
     *   INTER-UI NAVIGATION CONTROLS
     */
    
    
    
    //CHECKBOX CONTORL
    SB('.checkem').click(function () {
        SB('.entry').attr('checked', this.checked);
    });
    
    
    //NOTES UPDATE FUNCTION
    SB('.notes').change(function (){
        SB.ajax({
            type: "POST",
            url: location.href,
            data: 'id='+(SB(this).attr('alt-data'))+'&note='+(encodeURIComponent(SB(this).val())),
            //success: alert(encodeURIComponent(SB(this).val()))
        });
    });
    
    
    //BOT|HUMAN INDICATOR UI CONTROL
    SB('.verified').click(function (){
        if(SB(this).text() == "HUMAN"){
            SB(this).text('BOT');
            SB(this).css('color', 'red');
            
            SB.ajax({
               type: "POST",
               url: location.href,
               data: 'id='+(SB(this).attr('alt-data'))+'&type=0',
               //success: alert(SB(this).attr('alt-data'))
            });
            
        }else{
            SB(this).text('HUMAN');
            SB(this).css('color', 'green');
            
            SB.ajax({
               type: "POST",
               url: location.href,
               data: 'id='+(SB(this).attr('alt-data'))+'&type=1',
               //success: alert(SB(this).attr('alt-data'))
            });
        }
    });
    
    
    
    
    /*
     *   UI CONFIGURATION FUNCTIONS
     */
    
    
    //check if key is valid and retuns xml to spotbot
    SB('#botscout_check_key').click(function () {
        if(SB('#spotbot_apikey').val() == ""){
            SB('#botscout_check_key').text('INVALID!');
            SB('#botscout_check_key').css('color', 'red');
        }else{
            
            var url = encodeURIComponent('http://www.botscout.com/test/?ip=' + SB(this).attr('ip-test') + '&key=' + SB('#spotbot_apikey').val() + '&format=xml');
            SB.ajax({
                type: "GET",
                url: "../wp-content/plugins/spotbot/check_bot.php?url=" + url,
                dataType: "xml",
                success: function(xml){
                    if(SB(xml).find('response').text()){
                        SB('#botscout_check_key').text('OK!');
                        SB('#botscout_check_key').css('color', 'green');
                    }
                },
                error: function(){
                    SB('#botscout_check_key').text('INVALID!');
                    SB('#botscout_check_key').css('color', 'red');
                }
            });
        }
        
    });
    
    
    /*
     *   UI FAQ FUNCTIONS
     */
    
    //redraw image maps
    SB('img[usemap]').rwdImageMaps();
    
    
});