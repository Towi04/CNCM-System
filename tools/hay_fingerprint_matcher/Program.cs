using System.Collections.Concurrent;
using System.Net.Http.Json;
using System.Text.Json;

var builder = WebApplication.CreateBuilder(args);
var cfg = builder.Configuration;

string hayBase = cfg["Hay:BaseUrl"]?.TrimEnd('/') ?? "https://cncmedum.edu.mx/hay";
string matcherKey = cfg["Hay:MatcherKey"] ?? "";
int idPlantel = int.TryParse(cfg["Hay:IdPlantel"], out var p) ? p : 1;
int syncMin = int.TryParse(cfg["Hay:GallerySyncMinutes"], out var m) ? m : 5;

var gallery = new GalleryStore();
var engine = new FingerJetEngine(cfg);

_ = Task.Run(async () =>
{
    while (true)
    {
        try
        {
            await gallery.SyncFromHayAsync(hayBase, idPlantel, matcherKey);
            Console.WriteLine($"[{DateTime.Now:HH:mm:ss}] Galería sincronizada: {gallery.Count} huellas (plantel {idPlantel})");
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[{DateTime.Now:HH:mm:ss}] Error sync galería: {ex.Message}");
        }
        await Task.Delay(TimeSpan.FromMinutes(syncMin));
    }
});

var app = builder.Build();

app.MapGet("/health", () => Results.Json(new
{
    ok = true,
    sdk = engine.SdkAvailable,
    sdk_message = engine.SdkMessage,
    gallery_count = gallery.Count,
    id_plantel = idPlantel,
    hay_base = hayBase,
}));

app.MapPost("/identify", async (IdentifyRequest req) =>
{
    if (string.IsNullOrWhiteSpace(req.Sample))
        return Results.Json(new { ok = false, message = "Muestra vacía" });

    if (req.IdPlantel > 0 && req.IdPlantel != idPlantel)
        return Results.Json(new { ok = false, message = "Plantel no configurado en este servicio" });

    var result = engine.Identify(req.Sample, gallery.Items);
    return Results.Json(result);
});

app.MapPost("/sync", async () =>
{
    await gallery.SyncFromHayAsync(hayBase, idPlantel, matcherKey);
    return Results.Json(new { ok = true, total = gallery.Count });
});

Console.WriteLine("HayFingerprintMatcher — escuchando en " + string.Join(", ", cfg["Matcher:Urls"] ?? "http://127.0.0.1:8765"));
Console.WriteLine("SDK FingerJet: " + (engine.SdkAvailable ? "disponible" : engine.SdkMessage));
app.Run();

record IdentifyRequest(string Sample, int IdPlantel);

sealed class GalleryItem
{
    public int IdAlumno { get; set; }
    public string CodigoHuella { get; set; } = "";
    public string Nombre { get; set; } = "";
    public string? TemplateFmd { get; set; }
    public List<string> Muestras { get; set; } = new();
}

sealed class GalleryStore
{
    private readonly object _lock = new();
    public List<GalleryItem> Items { get; private set; } = new();
    public int Count => Items.Count;

    public async Task SyncFromHayAsync(string hayBase, int idPlantel, string key)
    {
        using var http = new HttpClient { Timeout = TimeSpan.FromSeconds(60) };
        var url = $"{hayBase}/php/huella_matcher_api.php?action=gallery&id_plantel={idPlantel}&matcher_key={Uri.EscapeDataString(key)}";
        var doc = await http.GetFromJsonAsync<JsonElement>(url);
        if (!doc.TryGetProperty("items", out var arr) || arr.ValueKind != JsonValueKind.Array)
            throw new InvalidOperationException("Respuesta de galería inválida");

        var list = new List<GalleryItem>();
        foreach (var it in arr.EnumerateArray())
        {
            list.Add(new GalleryItem
            {
                IdAlumno = it.GetProperty("id_alumno").GetInt32(),
                CodigoHuella = it.GetProperty("codigo_huella").GetString() ?? "",
                Nombre = it.TryGetProperty("nombre", out var n) ? n.GetString() ?? "" : "",
                TemplateFmd = it.TryGetProperty("template_fmd", out var f) && f.ValueKind == JsonValueKind.String
                    ? f.GetString() : null,
                Muestras = it.TryGetProperty("muestras", out var ms) && ms.ValueKind == JsonValueKind.Array
                    ? ms.EnumerateArray().Select(x => x.GetString() ?? "").Where(s => s != "").ToList()
                    : new List<string>(),
            });
        }

        lock (_lock) { Items = list; }
    }
}

/// <summary>
/// Motor FingerJet (U.are.U SDK). Sin SDK instalado usa respaldo heurístico limitado.
/// </summary>
sealed class FingerJetEngine
{
    private readonly IConfiguration _cfg;

#if FINGERJET_SDK
    public bool SdkAvailable => true;
    public string SdkMessage => "U.are.U SDK / DPUruNet cargado";
#else
    public bool SdkAvailable => false;
    public string SdkMessage => "Instale U.are.U SDK Windows y compile con FINGERJET_SDK (ver README)";
#endif

    public FingerJetEngine(IConfiguration cfg) => _cfg = cfg;

    public object Identify(string probeSample, List<GalleryItem> gallery)
    {
        if (gallery.Count == 0)
            return new { ok = false, message = "Galería vacía. Sincronice desde HAY o registre huellas." };

#if FINGERJET_SDK
        // TODO: Importar FMD de probeSample, comparar 1:N con FingerJet Identify
        // var probeFmd = FeatureExtraction.CreateFmdFromFid(...);
        // var result = Comparison.Identify(probeFmd, galleryFmds, threshold, ...);
        return new { ok = false, message = "Implemente Identify con DPUruNet (ver README sección SDK)" };
#else
        return IdentifyHeuristic(probeSample, gallery);
#endif
    }

    private static object IdentifyHeuristic(string probe, List<GalleryItem> gallery)
    {
        string Norm(string s) => s.Replace('-', '+').Replace('_', '/');
        double Score(string a, string b)
        {
            var na = Norm(a); var nb = Norm(b);
            if (na == nb) return 1.0;
            int min = Math.Min(na.Length, nb.Length);
            int max = Math.Max(na.Length, nb.Length);
            if (min == 0) return 0;
            int eq = 0;
            for (int i = 0; i < min; i++) if (na[i] == nb[i]) eq++;
            return (double)eq / max;
        }

        GalleryItem? best = null;
        double bestScore = 0, second = 0;
        foreach (var g in gallery)
        {
            foreach (var m in g.Muestras)
            {
                var sc = Score(probe, m);
                if (sc > bestScore) { second = bestScore; bestScore = sc; best = g; }
                else if (sc > second) second = sc;
            }
        }

        if (best != null && bestScore >= 0.72 && (bestScore - second) >= 0.05)
        {
            return new { ok = true, codigo_huella = best.CodigoHuella, id_alumno = best.IdAlumno, nombre = best.Nombre, score = bestScore, engine = "heuristic" };
        }

        return new
        {
            ok = false,
            message = "Huella no reconocida. Instale el SDK FingerJet para planteles grandes (~600 alumnos).",
            engine = "heuristic",
        };
    }
}
