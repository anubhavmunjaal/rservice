
<script>
    (function($){
      "use strict";
  
      $(document).ready(function(){

        $(document).on('click','.country-wrapper .list li' ,function() {
          let country_id = $("#service_country_id").val();
          $.ajax({
            method: 'post',
            url: "{{ route('user.country.city') }}",
            data: {
              country_id: country_id
            },
            success: function(res) {
              if (res.status == 'success') {
                let alloptions = "<option value=''>{{__('Select City')}}</option>";
                let allList = "<li class='option' data-value=''>{{__('Select City')}}</li>";
                let allCity = res.cities;
                $.each(allCity, function(index, value) {
                  alloptions += "<option value='" + value.id +
                          "'>" + value.service_city + "</option>";
                  allList += "<li class='option' data-value='" + value.id +
                          "'>" + value.service_city + "</li>";
                });
                $("#service_city_id").html(alloptions);
                $("#service_city_id").parent().find(".current").html('Select City');
                $("#service_city_id").parent().find(".list").html(allList);
              }
            }
          })
        })


        $(document).on('keyup','#home_search',function(e){
            e.preventDefault();
            let search_text = $(this).val();
            let service_city_id = $('#service_city_id').val();
            let country_id = $("#service_country_id").val();
            if(search_text.length > 0){
                $('#home_search').parent().find('button[type="submit"] i').addClass('la-spin la-spinner').removeClass('la-search');    
                
              $.ajax({
                  url:"{{ route('frontend.home.search') }}",
                  method:"get",
                  data:{
                    search_text:search_text,
                    country_id:country_id,
                    service_city_id:service_city_id,
                  },
                  success:function(res){
                      $('#home_search').parent().find('button[type="submit"] i').removeClass('la-spin la-spinner').addClass('la-search'); 
                      if (res.status == 'success') {
                          $('#all_search_result').html(res.result);
                      }else{
                        $('#all_search_result').html(res.result);
                      }
                      $('#all_search_result').show();
                  }
              });
            }else{
              $('#all_search_result').hide();
            }
              
          })
          
      });
  })(jQuery);
  </script>
  
  