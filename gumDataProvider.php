<?php
namespace Xibo\Custom;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\Widget\Provider\DurationProviderInterface;
use Xibo\Widget\Provider\WidgetProviderInterface;
use Xibo\Widget\Provider\WidgetProviderTrait;

class gumDataProvider implements WidgetProviderInterface
{
    use WidgetProviderTrait;

    public function fetchData(DataProviderInterface $dataProvider): WidgetProviderInterface
    {
        // Criando uma instância do cliente HTTP (Guzzle)
        $client = new Client();


        // URL do feed RSS do G1
        $url = $dataProvider->getProperty('uri');

        if (empty($url)) {
            throw new InvalidArgumentException(__('Please enter the URI to a valid RSS feed.'), 'uri');
        }
        

        // Realizando a requisição GET para o feed RSS
        $response = $client->get($url);
        $rssContent = $response->getBody()->getContents();

        // Carregando o XML
        $rss = simplexml_load_string($rssContent, "SimpleXMLElement", LIBXML_NOCDATA);

        // Determinar o número total de itens disponíveis no feed (ou no máximo 10)
        $totalItems = min(count($rss->channel->item), 10); // Limita o total a 10

        // Gerar um índice aleatório dentro do intervalo válido
        $aleatorio = rand(0, $totalItems - 1);

        // Pegar uma notícia aleatória
        $item = $rss->channel->item[$aleatorio];

        $title = (string) $item->title; // Título da notícia
        $mediaContent = $item->children('media', true)->content->attributes()->url ?? null; // URL da imagem

        if ($mediaContent) {
            // URL da logo do G1
            $logoUrl = 'https://raw.githubusercontent.com/henriquelucas/modulo-g1-rss-xibo/refs/heads/main/g1.png';

            // Gerar o HTML com a imagem responsiva, título no rodapé e logo no canto superior esquerdo
            $html = "
            <div style='
                display: flex;
                align-items: center;
                justify-content: center;
                width: 1920px;
                height: 1080px;
                background-color: #000;'>

                <div style='
                    width: 100%;
                    height: 100%;
                    position: relative;'>
                    
                    <!-- Imagem de fundo -->
                    <div style='
                        width: 100%;
                        height: 100%;
                        background-image: url(\"{$mediaContent}\");
                        background-size: cover;
                        background-position: center;
                        position: absolute;
                        top: 0;
                        left: 0;
                        z-index: 1;'>

                    </div>
                    
                    <!-- Background preto semi-transparente -->
                    <div style='
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0, 0, 0, 0.5); /* Transparência no fundo */
                        z-index: 2;'>
                    </div>
                    
                    <!-- Logo do G1 -->
                    <img src=\"{$logoUrl}\" alt=\"G1 Logo\" style='
                        position: absolute;
                        top: 20px;
                        left: 20px;
                        width: 200px;
                        height: auto;
                        z-index: 3;'>

                    <!-- Título da notícia -->
                    <div style='
                        position: absolute;
                        bottom: 0;
                        width: 100%;
                        background: linear-gradient(to top, rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0));
                        color: white;
                        text-align: left;
                        padding: 20px;
                        font-size: 80px;
                        word-wrap: break-word;
                        overflow-wrap: break-word;
                        z-index: 3;'>
                        <p style='margin: 0; padding: 20px; text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);font-weight: 600;'>{$title}</p>
                    </div>

                </div>
            </div>";

            // Adicionando os dados ao provider
            $dataProvider->addItem([
                'subject' => $title,
                'body' => $html,
                'date' => Carbon::now(),
                'createdAt' => Carbon::now(),
            ]);
        }

        // Marcando que os dados foram processados
        $dataProvider->setIsHandled();

        return $this;
    }

    public function fetchDuration(DurationProviderInterface $durationProvider): WidgetProviderInterface
    {
        return $this;
    }

    public function getDataCacheKey(DataProviderInterface $dataProvider): ?string
    {
        // Retorna uma chave única para o cache, com base no módulo e nos dados
        return 'gumDataProvider-' . md5('G1RSSFeed');
    }
    
    public function getDataModifiedDt(DataProviderInterface $dataProvider): ?Carbon
    {
        // Define o tempo de cache (em segundos)
        $cacheDuration = 600; // 10 minutos
    
        // Retorna a data/hora em que os dados foram modificados
        return Carbon::now()->subSeconds($cacheDuration);
    }
}
