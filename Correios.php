<?php

class Correios {

    private $DB;
    private $cep;
    private $url = 'https://api.correios.com.br';
    private $codAcesso;
    private $user = '';
    private $cartaoPostagem = ''; // SEU CARTÃO POSTAGEM DOS CORREIOS
    private $token;
    private $contrato = ''; // SEU CONTRATO DOS CORREIOS    
    private $pac = ''; // CODIGO PAC DO SEUS CORREIOS
    private $sedex = ''; // CODIGO SEDEX DO SEUS CORREIOS
    // ADICIONAR MAIS CODIGOS AQUI

    public function __construct($DB) {
        $this->DB = $DB;
        
        $this->cep = ''; // SEU CEP DE ORIGEM
    }

    public function request($uri, $metodo, $body) {
        
        $lnExpira = ''; // SUA DATA SALVA DE EXPIRAÇÃO  NO BANCO ETC;
        
        if($lnExpira->dataExpiraCorreios <= strtotime('Y-m-d H:i:s', date('-30 minutes'))) {
            $token = $this->geraToken();  
        } 
        
        $ch = curl_init($this->url . $uri);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metodo); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->setHeaders());

        if(!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }   

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        
        $retorno = json_decode($response);

        return (object)['statusHttp' => $http_status, 'retorno' => $retorno];

    }
    
    public function calculaFrete($dadosCarrinho, $cepDestino, $transportadora) {
        
        $transportadoraCod = $this->setTransportadora($transportadora);
        
        if($dadosCarrinho->pesoKG < 1) {
            $dadosCarrinho->pesoKG = 1;
        }
        
        $body = '{
          "idLote" : "1",
          "parametrosProduto": [
            {
              "coProduto": "'.$transportadoraCod.'",
              "nuRequisicao": "1",
              "nuContrato": "", // NUMERO DO SEU CONTRATO
              "nuDR" : , // NUMERO DA SUA DR DOS CORREIOS
              "cepOrigem": "'.$this->cep.'",
              "psObjeto": "'.round(intval($dadosCarrinho->pesoKG)).'",
              "tpObjeto": "2",
              "comprimento": "'.$dadosCarrinho->comprimento.'",
              "largura": "'.$dadosCarrinho->largura.'",
              "altura": "'.$dadosCarrinho->altura.'",
              "cepDestino": "'.$cepDestino.'"
            }
          ]
        }';
        
        $retornoPreco = $this->request('/preco/v1/nacional', 'POST', $body);
        
        if(!in_array($retornoPreco->statusHttp, [200,201])) {
            throw new Exception('Erro ao consultar frete');
        }
        
        $precoEntrega = $retornoPreco->retorno[0]->pcFinal;
        
        $bodyPrazo = '{
          "idLote": "1",
          "parametrosPrazo": [
            {
              "coProduto": "'.$transportadoraCod.'",
              "nuRequisicao": "1",
              "cepOrigem": "'.$this->cep.'",
              "cepDestino": "'.$cepDestino.'"
            }
          ]
        }';
        
        $retornoPrazo = $this->request('/prazo/v1/nacional', 'POST', $bodyPrazo);

        if(!in_array($retornoPrazo->statusHttp, [200,201])) {
            throw new Exception('Erro ao consultar frete');
        }
    
        $prazoEntrega = $retornoPrazo->retorno[0]->prazoEntrega;
        
        return (object)['Prazo' => $prazoEntrega, 'Preco' => $precoEntrega];
    }
    
    private function setTransportadora($transportadora) {
        
        switch ($transportadora) {
            case 'SEDEX':
                return $this->sedex;
                break;
            case 'PAC':
                return $this->pac;
                break;
            default:
                throw new Exception('Transportadora não encontrada');
        }
        
    }

    private function setHeaders($tipo = '') {
        
        if($tipo == 'token') {
            
            $credenciais = base64_encode($this->user . ':' . $this->codAcesso);
        
            return [
                'accept: application/json',
                'Content-Type: application/json',
                'Authorization: Basic ' . $credenciais
            ];
        }
        
        $lnToken     = ''; // SEU TOKEN DOS CORREIOS;
        
        $this->token = $lnToken->tokenCorreio;
        
        return [
            'accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        ];
    }

    public function geraToken() {
        
        $lnAcesso = ''; // SEU CODIGO DE ACESSO DOS CORREIOS;
        
        if($lnAcesso == false) {
            throw new Exception('Sem codigo acesso correios');
        }
        
        $this->codAcesso = $lnAcesso->codAcessoCorreio;

        $ch = curl_init($this->url . '/token/v1/autentica/cartaopostagem');
        
        $body = '{
          "numero": "'.$this->cartaoPostagem.'"
        }';

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST'); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->setHeaders('token'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        
        $retorno = json_decode($response);
        
        if(!in_array($http_status, [200,201])) {
            throw new Exception((object)['status' => 0, 'mensagem' => 'Erro ao gerar token']);        
        } 
        
        // SALVA NO BANCO A DATA DE EXPIRAÇÃO E O TOKEN
        
        return (object)['status' => 1, 'statusHttp' => $http_status, 'retorno' => $retorno];

        
    }

}