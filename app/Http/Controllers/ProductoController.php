<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Producto;
use App\Models\Marca;
use App\Models\Proveedor;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class ProductoController extends Controller
{
    /**
     * Subida de imágenes a Cloudinary con fallback por API HTTP
     */
    private function subirImagen($archivo, string $carpeta): ?string
    {
        $cloudinaryUrl = config('cloudinary.cloud_url') ?? env('CLOUDINARY_URL');

        if (!$cloudinaryUrl) {
            Log::warning("Cloudinary sin configurar (falta CLOUDINARY_URL en .env). No se subió la imagen en '{$carpeta}'.");
            return null;
        }

        try {
            $subida = Cloudinary::upload($archivo->getRealPath(), ['folder' => $carpeta]);

            if (is_object($subida) && method_exists($subida, 'getSecurePath')) {
                return $subida->getSecurePath();
            }
            if (is_array($subida) && isset($subida['secure_url'])) {
                return $subida['secure_url'];
            }
        } catch (\Throwable $e) {
            Log::error("Fallo con paquete Cloudinary (" . $e->getMessage() . "). Iniciando subida directa por HTTP REST API...");
        }

        try {
            if (preg_match('/cloudinary:\/\/([^:]+):(.+)@([^@\/]+)$/', trim($cloudinaryUrl), $matches)) {
                $apiKey    = $matches[1];
                $apiSecret = $matches[2];
                $cloudName = $matches[3];

                $timestamp = time();
                $stringToSign = "folder={$carpeta}&timestamp={$timestamp}" . $apiSecret;
                $signature    = sha1($stringToSign);

                $response = Http::attach(
                    'file',
                    file_get_contents($archivo->getRealPath()),
                    $archivo->getClientOriginalName()
                )->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                    'api_key'   => $apiKey,
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                    'folder'    => $carpeta,
                ]);

                if ($response->successful()) {
                    return $response->json('secure_url');
                }

                Log::error("Error en API REST de Cloudinary: " . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error("Fallo crítico en subida fallback por HTTP: " . $e->getMessage());
        }

        return null;
    }

    public function inicio()
    {
        $productos = Producto::with(['marca', 'proveedor'])
            ->orderBy('nombre')
            ->orderByRaw('CAST(talla AS DECIMAL(4,1))')
            ->get();

        // Un alta con varias tallas crea un producto por talla (mismo nombre,
        // marca, precio, etc.). Para que la lista no se vea como una fila
        // repetida por cada talla, se agrupan aquí por los campos que
        // comparten y se muestran las tallas juntas en una sola fila.
        $grupos = $productos->groupBy(function ($producto) {
            return implode('|', [
                $producto->nombre,
                $producto->descripcion,
                $producto->marca_id,
                $producto->proveedor_id,
                $producto->modelo,
                $producto->color,
                $producto->sexo,
                $producto->categoria,
                $producto->precio,
                $producto->estatus,
            ]);
        })->values();

        return response()->json(compact('grupos'));
    }

    // Tallas de calzado disponibles para elegir en el alta masiva (no viene de la BD).
    private function tallasDisponibles()
    {
        $tallas = [];
        for ($t = 22; $t <= 30; $t += 0.5) {
            $tallas[] = rtrim(rtrim(number_format($t, 1), '0'), '.');
        }
        return $tallas;
    }

    // 'categoria' es un texto libre en la BD (no hay tabla de categorías);
    // aquí solo se recopilan los valores que ya se han usado para ofrecerlos
    // como opciones y evitar variantes tipo "Tenis"/"tenis"/"Tennis".
    private function categoriasDisponibles()
    {
        return Producto::whereNotNull('categoria')
            ->where('categoria', '!=', '')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');
    }

    public function formulario()
    {
        $marcas = Marca::all();
        $proveedores = Proveedor::all();
        $tallas = $this->tallasDisponibles();
        $categorias = $this->categoriasDisponibles();

        return response()->json(compact('marcas', 'proveedores', 'tallas', 'categorias'));
    }

    public function editar(Request $request)
    {
        $id = $request->route('id');
        $producto = Producto::find($id);
        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }
        $marcas = Marca::all();
        $proveedores = Proveedor::all();
        $categorias = $this->categoriasDisponibles();

        return response()->json(compact('producto', 'marcas', 'proveedores', 'categorias'));
    }

    public function actualizar(Request $request)
    {
        $id = $request->route('id');
        $producto = Producto::find($id);
        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $hermanos = Producto::where('id', '!=', $producto->id)
            ->where('nombre', $producto->nombre)
            ->where('descripcion', $producto->descripcion)
            ->where('marca_id', $producto->marca_id)
            ->where('proveedor_id', $producto->proveedor_id)
            ->where('modelo', $producto->modelo)
            ->where('color', $producto->color)
            ->where('sexo', $producto->sexo)
            ->where('categoria', $producto->categoria)
            ->where('precio', $producto->precio)
            ->where('estatus', $producto->estatus)
            ->get();

        $camposCompartidos = [
            'nombre' => $request->input('nombre'),
            'descripcion' => $request->input('descripcion'),
            'marca_id' => $request->input('marca_id'),
            'proveedor_id' => $request->input('proveedor_id'),
            'precio' => $request->input('precio'),
            'modelo' => $request->input('modelo'),
            'color' => $request->input('color'),
            'sexo' => $request->input('sexo'),
            'categoria' => $request->input('categoria'),
        ];

        foreach ($hermanos as $hermano) {
            $hermano->fill($camposCompartidos);
            $hermano->save();
        }

        $producto->nombre = $request->input('nombre');
        $producto->descripcion = $request->input('descripcion');
        $producto->marca_id = $request->input('marca_id');
        $producto->proveedor_id = $request->input('proveedor_id');
        $producto->precio = $request->input('precio');
        $producto->modelo = $request->input('modelo');
        $producto->color = $request->input('color');
        $producto->sexo = $request->input('sexo');
        $producto->categoria = $request->input('categoria');
        $producto->talla = $request->input('talla');
        $producto->estatus = $request->input('estatus');
        $producto->save();

        foreach (['imagen1', 'imagen2', 'imagen3'] as $campo) {
            if ($request->hasFile($campo) && $request->file($campo)->isValid()) {
                $url = $this->subirImagen($request->file($campo), 'productos');
                if ($url) {
                    $producto->{$campo} = $url;
                    $producto->save();
                }
            }
        }

        $mensaje = $hermanos->isNotEmpty()
            ? 'Producto actualizado (se aplicó a las ' . ($hermanos->count() + 1) . ' tallas de este modelo).'
            : 'Producto actualizado';

        return response()->json(['message' => $mensaje]);
    }

    public function guardar(Request $request)
    {
        $tallas = array_filter($request->input('tallas', []));
        if (empty($tallas)) {
            return response()->json(['message' => 'Selecciona al menos una talla'], 422);
        }

        // Las imágenes se suben una sola vez y se comparten entre todas las
        // tallas creadas (es el mismo modelo de zapato, solo cambia la talla).
        $urls = ['imagen1' => null, 'imagen2' => null, 'imagen3' => null];
        foreach (array_keys($urls) as $campo) {
            if ($request->hasFile($campo) && $request->file($campo)->isValid()) {
                $urls[$campo] = $this->subirImagen($request->file($campo), 'productos');
            }
        }

        foreach ($tallas as $talla) {
            $producto = new Producto();
            $producto->nombre = $request->input('nombre');
            $producto->descripcion = $request->input('descripcion');
            $producto->marca_id = $request->input('marca_id');
            $producto->proveedor_id = $request->input('proveedor_id');
            $producto->precio = $request->input('precio');
            $producto->modelo = $request->input('modelo');
            $producto->color = $request->input('color');
            $producto->sexo = $request->input('sexo');
            $producto->categoria = $request->input('categoria');
            $producto->talla = $talla;
            $producto->estatus = $request->input('estatus');
            $producto->imagen1 = $urls['imagen1'] ?? 'sin-imagen.jpg';
            $producto->imagen2 = $urls['imagen2'];
            $producto->imagen3 = $urls['imagen3'];
            $producto->save();
        }

        $mensaje = count($tallas) > 1
            ? count($tallas) . ' productos guardados (uno por talla).'
            : 'Producto guardado exitosamente.';

        return response()->json(['message' => $mensaje], 201);
    }

    public function eliminar(Request $request)
    {
        $id = $request->route('id');
        $producto = Producto::find($id);
        
        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        // No se puede borrar de la BD si ya tiene inventario o pedidos
        // ligados (llave foránea) — se marca Agotado en vez de tronar.
        if ($producto->inventarios()->exists() || $producto->detallePedidos()->exists()) {
            $producto->estatus = 'Agotado';
            $producto->save();

            return response()->json([
                'message' => '"' . $producto->nombre . '" (talla ' . $producto->talla . ') tiene inventario o pedidos registrados, '
                    . 'así que no se puede eliminar por completo — se marcó como Agotado en su lugar.'
            ]);
        }

        $producto->delete();
        return response()->json(['message' => 'Producto eliminado']);
    }

    public function mostrar(Request $request)
    {
        $id = $request->route('id');
        $producto = Producto::find($id);
        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }
        return response()->json(['producto' => $producto]);
    }
}
