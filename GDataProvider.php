<?php
namespace Xibo\Custom;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\Widget\Provider\DurationProviderInterface;
use Xibo\Widget\Provider\WidgetProviderInterface;
use Xibo\Widget\Provider\WidgetProviderTrait;

class GDataProvider implements WidgetProviderInterface
{
    use WidgetProviderTrait;

    // Propriedade para armazenar o intervalo de atualização
    private $updateInterval = 2; // Valor padrão de 2 minutos

    // Método para definir o intervalo de atualização
    public function setUpdateInterval(int $minutes): self
    {
        $this->updateInterval = $minutes;
        return $this;
    }

    // Método para obter o intervalo de atualização
    public function getUpdateInterval(): int
    {
        return $this->updateInterval;
    }

    public function fetchData(DataProviderInterface $dataProvider): WidgetProviderInterface
{
    // Criando uma instância do cliente HTTP (Guzzle)
    $client = new Client();

    // URL do feed RSS do G1
    $url = 'https://g1.globo.com/rss/g1/pb/paraiba/';

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

    // Extrair a imagem do item
    $imagem = '';
    if (isset($item->children('media', true)->content)) {
        $mediaContent = $item->children('media', true)->content;
        $imagem = (string) $mediaContent->attributes()->url; // Extrai a URL da imagem
    }

    // Criar a tag <img> responsiva
    $imagemResponsiva = '<img src="' . $imagem . '" style="width: 100%; height: 100%; object-fit: fill;"  alt="' . $title . '" />';

    // Adicionando os dados ao provider
    $dataProvider->addItem([
        'titulo' => $title,
        'corpo' => $imagemResponsiva, // Aqui você adiciona a tag <img> responsiva
        'date' => Carbon::now(),
        'createdAt' => Carbon::now(),
    ]);

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
        $cacheDuration = 120; // 2 minutos (120 segundos)

        // Retorna a data/hora em que os dados foram modificados
        return Carbon::now()->subSeconds($cacheDuration);
    }
}