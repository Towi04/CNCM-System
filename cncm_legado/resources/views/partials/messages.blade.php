<script type="text/javascript">
    $(document).ready(function() {
        toastr.options = {
            closeButton: true,
            progressBar: true,
            showMethod: 'slideDown',
            timeOut: 5000
        };


        @if(Session::has('message'))
            toastr.success('Éxito', '{{ Session::get('message') }}');
        @endif

        @if(Session::has('error'))
            toastr.error('Atención', '{{ Session::get('error') }}');
        @endif

        @if($errors->any())
            toastr.error('Atención', 'Corrige los siguientes errores:  {{ Session::get('error') }} @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach');
        @endif
    });
</script>

<div aria-hidden="true" aria-labelledby="modalCargando" class="modal fade" id="modalCargando" role="dialog" tabindex="-1" data-keyboard="false" data-backdrop="false">
    <div class="modal-dialog modal-sm " role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">
           <center><i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i> <br>Se esta procesando la información. Por favor espere</center>
          </h5>
        </div>
    </div>
  </div>
</div>

<script type="text/javascript">
    var wait = $('#modalCargando');
</script>
