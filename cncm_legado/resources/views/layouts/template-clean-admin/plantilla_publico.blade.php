<!DOCTYPE html>
<html>
  <head>
    <title>{{config('app.name')}}</title>
    <meta charset="utf-8">
    <meta content="ie=edge" http-equiv="x-ua-compatible">
    <meta content="Aldo Aranza" name="author">
    <meta content="Página de administración de campo Real" name="description">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="{{asset('img/logo.png')}}" rel="shortcut icon">
    <link href="apple-touch-icon.png" rel="apple-touch-icon">
    
    <link href="https://fonts.googleapis.com/css?family=Rubik:300,400,500" rel="stylesheet" type="text/css">
    <link href="{{asset('template-'.config('settings.template').'/bower_components/select2/dist/css/select2.min.css')}}" rel="stylesheet">
    <link href="{{asset('template-'.config('settings.template').'/bower_components/bootstrap-daterangepicker/daterangepicker.css')}}" rel="stylesheet">
    <link href="{{asset('template-'.config('settings.template').'/bower_components/dropzone/dist/dropzone.css')}}" rel="stylesheet">
    {{-- <link href="{{asset('template-'.config('settings.template').'/bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css')}}" rel="stylesheet"> --}}
    <link href="{{asset('css/plugins/dataTables/datatables.min.css')}}" rel="stylesheet">
    
    
    <link href="{{asset('template-'.config('settings.template').'/bower_components/fullcalendar/dist/fullcalendar.min.css')}}" rel="stylesheet">
    <link href="{{asset('template-'.config('settings.template').'/bower_components/perfect-scrollbar/css/perfect-scrollbar.min.css')}}" rel="stylesheet">
    <link href="{{asset('template-'.config('settings.template').'/bower_components/slick-carousel/slick/slick.css')}}" rel="stylesheet">
    <link href="{{asset('template-'.config('settings.template').'/css/main.css?version=4.3.0')}}" rel="stylesheet">
    <link href="{{asset('js/plugins/dropify/dist/css/dropify.min.css') }}" rel="stylesheet" >
    <link href="{{asset('css/plugins/datapicker/datepicker3.css')}}" rel="stylesheet">
    <script src="https://kit.fontawesome.com/1f556c46ab.js" crossorigin="anonymous"></script>
    <link href="{{asset('css/plugins/toastr/toastr.min.css')}}" rel="stylesheet">
    <link href="{{asset('css/plugins/colorpicker/bootstrap-colorpicker.min.css')}}" rel="stylesheet">

    <link href="{{ asset('css/plugins/sweetalert/sweetalert.css') }}" rel="stylesheet" type="text/css">
    <script src="{{asset('plugins/dragula.js/dist/dragula.min.js')}}"></script>
    <style>
      
		@media print{

a[href]:after {
  content: none !important;
}

.no_print{
  display: none;
}
#version_imprimible, #version_imprimible > *{
  display: inline !important;
}

td, td > *{
  font-size: 8px;
}

th, th > *{
  font-size: 8px;
}

}
@media screen{
#version_imprimible{
  display: none;
}
}

</style>

<style>
  .contact-box:hover{
      transform: scale(1.05)
  }
  .table-responsive {
      overflow-x: auto !important;
  }
  
  .content-w {
      overflow: hidden !important;
  }
  td:hover{
      /* background-color: #abd0ea; */
      /* cursor:pointer; */
  }
  
  .table-responsive>.fixed-column {
      position: absolute;
      display: inline-block;
      width: auto;
      border-right: 1px solid #ddd;
  }

  .top-bar.color-scheme-bright {
    background-color: #b4440c;
}

.top-bar.color-scheme-bright h4,.top-bar.color-scheme-bright i {
  color: #fff;
}

.breadcrumb {
    background: #999999;
    border-radius: 0px;
}

.breadcrumb li a {
    color: #fff;
    
}

.breadcrumb-item.active {
    color: #ffffff;
}


  @media(min-width:768px) {
      .table-responsive>.fixed-column {
          display: none;
      }
  }
  table.dataTable {
      clear: both;
      margin-top: 0px !important;
      margin-bottom: 0px !important;
  }
  </style>
  </head>
  <body class="menu-position-side menu-side-left full-screen with-content-panel">
    <div class="all-wrapper solid-bg-all">
      {{-- <div aria-hidden="true" class="onboarding-modal modal fade animated show-on-load" role="dialog" tabindex="-1">
        <div class="modal-dialog modal-centered" role="document">
          <div class="modal-content text-center">
            <button aria-label="Close" class="close" data-dismiss="modal" type="button"><span class="close-label">Skip Intro</span><span class="os-icon os-icon-close"></span></button>
            <div class="onboarding-slider-w">
              <div class="onboarding-slide">
                <div class="onboarding-media">
                  <img alt="" src="img/bigicon2.png" width="200px">
                </div>
                <div class="onboarding-content with-gradient">
                  <h4 class="onboarding-title">
                    Example of onboarding screen!
                  </h4>
                  <div class="onboarding-text">
                    This is an example of a multistep onboarding screen, you can use it to introduce your customers to your app, or collect additional information from them before they start using your app.
                  </div>
                </div>
              </div>
              <div class="onboarding-slide">
                <div class="onboarding-media">
                  <img alt="" src="img/bigicon5.png" width="200px">
                </div>
                <div class="onboarding-content with-gradient">
                  <h4 class="onboarding-title">
                    Example Request Information
                  </h4>
                  <div class="onboarding-text">
                    In this example you can see a form where you can request some additional information from the customer when they land on the app page.
                  </div>
                  <form>
                    <div class="row">
                      <div class="col-sm-6">
                        <div class="form-group">
                          <label for="">Your Full Name</label><input class="form-control" placeholder="Enter your full name..." type="text" value="">
                        </div>
                      </div>
                      <div class="col-sm-6">
                        <div class="form-group">
                          <label for="">Your Role</label><select class="form-control">
                            <option>
                              Web Developer
                            </option>
                            <option>
                              Business Owner
                            </option>
                            <option>
                              Other
                            </option>
                          </select>
                        </div>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
              <div class="onboarding-slide">
                <div class="onboarding-media">
                  <img alt="" src="img/bigicon6.png" width="200px">
                </div>
                <div class="onboarding-content with-gradient">
                  <h4 class="onboarding-title">
                    Showcase App Features
                  </h4>
                  <div class="onboarding-text">
                    In this example you can showcase some of the features of your application, it is very handy to make new users aware of your hidden features. You can use boostrap columns to split them up.
                  </div>
                  <div class="row">
                    <div class="col-sm-6">
                      <ul class="features-list">
                        <li>
                          Fully Responsive design
                        </li>
                        <li>
                          Pre-built app layouts
                        </li>
                        <li>
                          Incredible Flexibility
                        </li>
                      </ul>
                    </div>
                    <div class="col-sm-6">
                      <ul class="features-list">
                        <li>
                          Boxed & Full Layouts
                        </li>
                        <li>
                          Based on Bootstrap 4
                        </li>
                        <li>
                          Developer Friendly     
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div> --}}
      {{-- <div class="search-with-suggestions-w">
        <div class="search-with-suggestions-modal">
          <div class="element-search">
            <input class="search-suggest-input" placeholder="Start typing to search..." type="text">
              <div class="close-search-suggestions">
                <i class="os-icon os-icon-x"></i>
              </div>
            </input>
          </div>
          <div class="search-suggestions-group">
            <div class="ssg-header">
              <div class="ssg-icon">
                <div class="os-icon os-icon-box"></div>
              </div>
              <div class="ssg-name">
                Projects
              </div>
              <div class="ssg-info">
                24 Total
              </div>
            </div>
            <div class="ssg-content">
              <div class="ssg-items ssg-items-boxed">
                <a class="ssg-item" href="users_profile_big.html">
                  <div class="item-media" style="background-image: url(img/company6.png)"></div>
                  <div class="item-name">
                    Integ<span>ration</span> with API
                  </div>
                </a><a class="ssg-item" href="users_profile_big.html">
                  <div class="item-media" style="background-image: url(img/company7.png)"></div>
                  <div class="item-name">
                    Deve<span>lopm</span>ent Project
                  </div>
                </a>
              </div>
            </div>
          </div>
          <div class="search-suggestions-group">
            <div class="ssg-header">
              <div class="ssg-icon">
                <div class="os-icon os-icon-users"></div>
              </div>
              <div class="ssg-name">
                Customers
              </div>
              <div class="ssg-info">
                12 Total
              </div>
            </div>
            <div class="ssg-content">
              <div class="ssg-items ssg-items-list">
                <a class="ssg-item" href="users_profile_big.html">
                  <div class="item-media" style="background-image: url(img/avatar1.jpg)"></div>
                  <div class="item-name">
                    John Ma<span>yer</span>s
                  </div>
                </a><a class="ssg-item" href="users_profile_big.html">
                  <div class="item-media" style="background-image: url(img/avatar2.jpg)"></div>
                  <div class="item-name">
                    Th<span>omas</span> Mullier
                  </div>
                </a><a class="ssg-item" href="users_profile_big.html">
                  <div class="item-media" style="background-image: url(img/avatar3.jpg)"></div>
                  <div class="item-name">
                    Kim C<span>olli</span>ns
                  </div>
                </a>
              </div>
            </div>
          </div>
          <div class="search-suggestions-group">
            <div class="ssg-header">
              <div class="ssg-icon">
                <div class="os-icon os-icon-folder"></div>
              </div>
              <div class="ssg-name">
                Files
              </div>
              <div class="ssg-info">
                17 Total
              </div>
            </div>
            <div class="ssg-content">
              <div class="ssg-items ssg-items-blocks">
                <a class="ssg-item" href="#">
                  <div class="item-icon">
                    <i class="os-icon os-icon-file-text"></i>
                  </div>
                  <div class="item-name">
                    Work<span>Not</span>e.txt
                  </div>
                </a><a class="ssg-item" href="#">
                  <div class="item-icon">
                    <i class="os-icon os-icon-film"></i>
                  </div>
                  <div class="item-name">
                    V<span>ideo</span>.avi
                  </div>
                </a><a class="ssg-item" href="#">
                  <div class="item-icon">
                    <i class="os-icon os-icon-database"></i>
                  </div>
                  <div class="item-name">
                    User<span>Tabl</span>e.sql
                  </div>
                </a><a class="ssg-item" href="#">
                  <div class="item-icon">
                    <i class="os-icon os-icon-image"></i>
                  </div>
                  <div class="item-name">
                    wed<span>din</span>g.jpg
                  </div>
                </a>
              </div>
              <div class="ssg-nothing-found">
                <div class="icon-w">
                  <i class="os-icon os-icon-eye-off"></i>
                </div>
                <span>No files were found. Try changing your query...</span>
              </div>
            </div>
          </div>
        </div>
      </div> --}}
      <div class="layout-w">
        <!--------------------
        START - Mobile Menu
        -------------------->
        <div class="menu-mobile menu-activated-on-click color-scheme-dark no_print" >
          <div class="mm-logo-buttons-w">
            <a class="mm-logo" href="{{url('/')}}"><img src="{{asset('img/logo-horizontal-negativo.png')}}"><span>{{config('app.name')}}</span></a>
            <div class="mm-buttons">
              {{-- <div class="content-panel-open">
                <div class="os-icon os-icon-grid-circles"></div>
              </div> --}}
              <div class="mobile-menu-trigger">
                <div class="os-icon os-icon-hamburger-menu-1"></div>
              </div>
            </div>
          </div>
          <div class="menu-and-user">
            
            <!--------------------
            START - Mobile Menu List
            -------------------->
            GRACIAS POR SU PREFERENCIA
            <!--------------------
            END - Mobile Menu List
            -------------------->
           
          </div>
        </div>
        <!--------------------
        END - Mobile Menu
        --------------------><!--------------------
        START - Main Menu
        -------------------->
        <div class="menu-w no_print no-print color-scheme-dark color-style-bright menu-position-side menu-side-left menu-layout-compact sub-menu-style-over sub-menu-color-bright selected-menu-color-light menu-activated-on-hover menu-has-selected-link">
          <div class="logo-w no_print">
            <a class="logo" href="{{url('/')}}">
              <center><img src="{{asset('img/logo-horizontal-negativo.png')}}" style="width:100% !important" alt=""></center>
            </a>
          </div>
          <div class="logged-user-w avatar-inline no_print">
            
          </div>
          {{-- <div class="menu-actions">
            <!--------------------
            START - Messages Link in secondary top menu
            -------------------->
            <div class="messages-notifications os-dropdown-trigger os-dropdown-position-right">
              <i class="os-icon os-icon-mail-14"></i>
              <div class="new-messages-count">
                12
              </div>
              <div class="os-dropdown light message-list">
                <ul>
                  <li>
                    <a href="#">
                      <div class="user-avatar-w">
                        
                      </div>
                      <div class="message-content">
                        <h6 class="message-from">
                          John Mayers
                        </h6>
                        <h6 class="message-title">
                          Account Update
                        </h6>
                      </div>
                    </a>
                  </li>
                  <li>
                    <a href="#">
                      <div class="user-avatar-w">
                        <img alt="" src="img/avatar2.jpg">
                      </div>
                      <div class="message-content">
                        <h6 class="message-from">
                          Phil Jones
                        </h6>
                        <h6 class="message-title">
                          Secutiry Updates
                        </h6>
                      </div>
                    </a>
                  </li>
                  <li>
                    <a href="#">
                      <div class="user-avatar-w">
                        <img alt="" src="img/avatar3.jpg">
                      </div>
                      <div class="message-content">
                        <h6 class="message-from">
                          Bekky Simpson
                        </h6>
                        <h6 class="message-title">
                          Vacation Rentals
                        </h6>
                      </div>
                    </a>
                  </li>
                  <li>
                    <a href="#">
                      <div class="user-avatar-w">
                        <img alt="" src="img/avatar4.jpg">
                      </div>
                      <div class="message-content">
                        <h6 class="message-from">
                          Alice Priskon
                        </h6>
                        <h6 class="message-title">
                          Payment Confirmation
                        </h6>
                      </div>
                    </a>
                  </li>
                </ul>
              </div>
            </div>
            <!--------------------
            END - Messages Link in secondary top menu
            --------------------><!--------------------
            START - Settings Link in secondary top menu
            -------------------->
            <div class="top-icon top-settings os-dropdown-trigger os-dropdown-position-right">
              <i class="os-icon os-icon-ui-46"></i>
              <div class="os-dropdown">
                <div class="icon-w">
                  <i class="os-icon os-icon-ui-46"></i>
                </div>
                <ul>
                  <li>
                    <a href="users_profile_small.html"><i class="os-icon os-icon-ui-49"></i><span>Profile Settings</span></a>
                  </li>
                  <li>
                    <a href="users_profile_small.html"><i class="os-icon os-icon-grid-10"></i><span>Billing Info</span></a>
                  </li>
                  <li>
                    <a href="users_profile_small.html"><i class="os-icon os-icon-ui-44"></i><span>My Invoices</span></a>
                  </li>
                  <li>
                    <a href="users_profile_small.html"><i class="os-icon os-icon-ui-15"></i><span>Cancel Account</span></a>
                  </li>
                </ul>
              </div>
            </div>
            <!--------------------
            END - Settings Link in secondary top menu
            --------------------><!--------------------
            START - Messages Link in secondary top menu
            -------------------->
            <div class="messages-notifications os-dropdown-trigger os-dropdown-position-right">
              <i class="os-icon os-icon-zap"></i>
              <div class="new-messages-count">
                4
              </div>
              <div class="os-dropdown light message-list">
                <div class="icon-w">
                  <i class="os-icon os-icon-zap"></i>
                </div>
                <ul>
                  <li>
                    <a href="#">
                      <div class="user-avatar-w">
                        <img alt="" src="img/avatar1.jpg">
                      </div>
                      <div class="message-content">
                        <h6 class="message-from">
                          John Mayers
                        </h6>
                        <h6 class="message-title">
                          Account Update
                        </h6>
                      </div>
                    </a>
                  </li>
                  <li>
                    <a href="#">
                      <div class="user-avatar-w">
                        <img alt="" src="img/avatar2.jpg">
                      </div>
                      <div class="message-content">
                        <h6 class="message-from">
                          Phil Jones
                        </h6>
                        <h6 class="message-title">
                          Secutiry Updates
                        </h6>
                      </div>
                    </a>
                  </li>
                  <li>
                    <a href="#">
                      <div class="user-avatar-w">
                        <img alt="" src="img/avatar3.jpg">
                      </div>
                      <div class="message-content">
                        <h6 class="message-from">
                          Bekky Simpson
                        </h6>
                        <h6 class="message-title">
                          Vacation Rentals
                        </h6>
                      </div>
                    </a>
                  </li>
                  <li>
                    <a href="#">
                      <div class="user-avatar-w">
                        <img alt="" src="img/avatar4.jpg">
                      </div>
                      <div class="message-content">
                        <h6 class="message-from">
                          Alice Priskon
                        </h6>
                        <h6 class="message-title">
                          Payment Confirmation
                        </h6>
                      </div>
                    </a>
                  </li>
                </ul>
              </div>
            </div>
            <!--------------------
            END - Messages Link in secondary top menu
            -------------------->
          </div> --}}
          {{-- <div class="element-search autosuggest-search-activator">
            <input placeholder="Start typing to search..." type="text">
          </div> --}}
          <h1 class="menu-page-header">
            Page Header
          </h1>
          <p class="text-center">
            ¡GRACIAS POR SU PREFERENCIA!
          </p>
         
        </div>
        <!--------------------
        END - Main Menu
        -------------------->
        <div class="content-w" style="min-height: 900px;" >
          <!--------------------
          START - Top Bar
          -------------------->
          <div class="top-bar color-scheme-bright no_print">
            <span class="ml-4 mt-2">
            
              <h4>@yield('titulo')</h4>
            </span>
              
            <!--------------------
            START - Top Menu Controls
            -------------------->
            <div class="top-menu-controls">
              {{-- <div class="element-search autosuggest-search-activator">
                <input placeholder="Start typing to search..." type="text">
              </div> --}}
              <!--------------------
              START - Messages Link in secondary top menu
              -------------------->
              {{-- <div class="messages-notifications os-dropdown-trigger os-dropdown-position-left">
                <i class="os-icon os-icon-mail-14"></i>
                <div class="new-messages-count">
                  12
                </div>
                <div class="os-dropdown light message-list">
                  <ul>
                    <li>
                      <a href="#">
                        <div class="user-avatar-w">
                          <img alt="" src="img/avatar1.jpg">
                        </div>
                        <div class="message-content">
                          <h6 class="message-from">
                            John Mayers
                          </h6>
                          <h6 class="message-title">
                            Account Update
                          </h6>
                        </div>
                      </a>
                    </li>
                    <li>
                      <a href="#">
                        <div class="user-avatar-w">
                          <img alt="" src="img/avatar2.jpg">
                        </div>
                        <div class="message-content">
                          <h6 class="message-from">
                            Phil Jones
                          </h6>
                          <h6 class="message-title">
                            Secutiry Updates
                          </h6>
                        </div>
                      </a>
                    </li>
                    <li>
                      <a href="#">
                        <div class="user-avatar-w">
                          <img alt="" src="img/avatar3.jpg">
                        </div>
                        <div class="message-content">
                          <h6 class="message-from">
                            Bekky Simpson
                          </h6>
                          <h6 class="message-title">
                            Vacation Rentals
                          </h6>
                        </div>
                      </a>
                    </li>
                    <li>
                      <a href="#">
                        <div class="user-avatar-w">
                          <img alt="" src="img/avatar4.jpg">
                        </div>
                        <div class="message-content">
                          <h6 class="message-from">
                            Alice Priskon
                          </h6>
                          <h6 class="message-title">
                            Payment Confirmation
                          </h6>
                        </div>
                      </a>
                    </li>
                  </ul>
                </div>
              </div> --}}
              <!--------------------
              END - Messages Link in secondary top menu
              --------------------><!--------------------
              START - Settings Link in secondary top menu
              -------------------->
              {{-- <div class="top-icon top-settings os-dropdown-trigger os-dropdown-position-left">
                <i class="os-icon os-icon-ui-46"></i>
                <div class="os-dropdown">
                  <div class="icon-w">
                    <i class="os-icon os-icon-ui-46"></i>
                  </div>
                  <ul>
                    <li>
                      <a href="users_profile_small.html"><i class="os-icon os-icon-ui-49"></i><span>Profile Settings</span></a>
                    </li>
                    <li>
                      <a href="users_profile_small.html"><i class="os-icon os-icon-grid-10"></i><span>Billing Info</span></a>
                    </li>
                    <li>
                      <a href="users_profile_small.html"><i class="os-icon os-icon-ui-44"></i><span>My Invoices</span></a>
                    </li>
                    <li>
                      <a href="users_profile_small.html"><i class="os-icon os-icon-ui-15"></i><span>Cancel Account</span></a>
                    </li>
                  </ul>
                </div>
              </div> --}}
              <!--------------------
              END - Settings Link in secondary top menu
              --------------------><!--------------------
              START - User avatar and menu in secondary top menu
              -------------------->
             
              <!--------------------
              END - User avatar and menu in secondary top menu
              -------------------->
            </div>
            <!--------------------
            END - Top Menu Controls
            -------------------->
          </div>
          <!--------------------
          END - Top Bar
          --------------------><!--------------------
          START - Breadcrumbs
          -------------------->
          {{-- <ul class="breadcrumb">
            <li class="breadcrumb-item">
              <a href="index.html">Home</a>
            </li>
            <li class="breadcrumb-item">
              <a href="index.html">Products</a>
            </li>
            <li class="breadcrumb-item">
              <span>Laptop with retina screen</span>
            </li>
          </ul> --}}
          <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
            @csrf
        </form>
          @yield('breadcrumb')
          <!--------------------
          END - Breadcrumbs
          -------------------->
          
          <div class="content-i">
            <div class="content-box " >
              <div class="row">
                <div class="col-sm-12">
                  <div class="element-wrapper">
                    @yield('contenido')
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="display-type"></div>
    </div>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/jquery/dist/jquery.min.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/popper.js/dist/umd/popper.min.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/moment/moment.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/chart.js/dist/Chart.min.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/select2/dist/js/select2.full.min.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/jquery-bar-rating/dist/jquery.barrating.min.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/ckeditor/ckeditor.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/bootstrap-validator/dist/validator.min.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/bootstrap-daterangepicker/daterangepicker.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/ion.rangeSlider/js/ion.rangeSlider.min.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/dropzone/dist/dropzone.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/editable-table/mindmup-editabletable.js')}}"></script>
    <script src="{{asset('js/plugins/dataTables/datatables.min.js') }}"></script>
    <script src="{{asset('js/plugins/dataTables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/fullcalendar/dist/fullcalendar.min.js')}}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.8.0/locale-all.js"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/perfect-scrollbar/js/perfect-scrollbar.jquery.min.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/tether/dist/js/tether.min.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/slick-carousel/slick/slick.min.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/bootstrap/js/dist/util.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/bootstrap/js/dist/alert.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/bootstrap/js/dist/button.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/bootstrap/js/dist/carousel.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/bootstrap/js/dist/collapse.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/bootstrap/js/dist/dropdown.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/bootstrap/js/dist/modal.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/bootstrap/js/dist/tab.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/bootstrap/js/dist/tooltip.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/bootstrap/js/dist/popover.js')}}"></script>
    <script src="{{asset('template-'.config('settings.template').'/bower_components/bootstrap/js/dist/collapse.js')}}"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
    {{-- <script src="{{asset('template-'.config('settings.template').'/js/demo_customizer.js?version=4.3.0')}}"></script> --}}
    <script src="{{asset('template-'.config('settings.template').'/js/main.js?version=4.3.0')}}"></script>
    <script src="{{asset('js/plugins/dropify/dist/js/dropify.min.js') }}"></script>
    <script src="{{asset('js/plugins/datapicker/bootstrap-datepicker.js') }}"></script>
    <script src="{{asset('js/plugins/toastr/toastr.min.js')}}"></script>
    <script src="{{asset('js/plugins/colorpicker/bootstrap-colorpicker.min.js')}}"></script>
    <script src="{{ asset('js/plugins/sweetalert/sweetalert.min.js') }}"></script>
    <script src="{{ asset('js/plugins/sweetalert/jquery.sweet-alert.custom.js') }}"></script>
    {{-- <script src="{{asset('js/doublescroll.js')}}"></script> --}}
    {{-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous"> --}}
    <script src="{{asset('highcharts/code/highcharts.js')}}"></script>
    <script src="https://code.highcharts.com/5.0.14/highcharts-more.js"></script>
    <script src="https://code.highcharts.com/5.0.14/modules/solid-gauge.js"></script>
    <script src="https://code.highcharts.com/5.0.14/highcharts-3d.js"></script>

    <script src="{{asset('highcharts/code/modules/exporting.js')}}"></script>
    <script src="{{asset('highcharts/code/modules/export-data.js')}}"></script>
    @include('partials.messages')
    
    <script>
      $(document).ready(function () {
                $.fn.datepicker.dates['es'] = {
                            days: ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"],
                            daysShort: ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"],
                            daysMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sá"],
                            months: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
                            monthsShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"],
                            today: "Hoy",
                            clear: "Borrar"
                        };  
    
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });

             });
    </script>
    @yield('scripts')


  </body>
</html>
