# Sistema Intranet - Administração com Controle de Acesso

Sistema completo de intranet com gerenciamento de usuários, setores e permissões.

## 🚀 Funcionalidades

### Autenticação
- Login seguro com senha criptografada
- Controle de sessão
- Registro de último acesso

### Painel Administrativo (Acesso Restrito)
- **Gerenciar Usuários**: Cadastrar, editar e excluir usuários
- **Gerenciar Setores**: Organizar departamentos da empresa
- **Gerenciar Permissões**: Configurar permissões por setor e módulo
- **Logs do Sistema**: Visualizar histórico de acessos e ações

### Sistema de Permissões
- Permissões granulares por módulo: Visualizar, Criar, Editar, Excluir
- Controle por setor
- Administradores têm acesso total

## 📋 Requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Apache/XAMPP
- Extensão MySQLi habilitada

## 🔧 Instalação

### 1. Configurar Banco de Dados

Execute o arquivo `database.sql` no seu banco de dados MySQL remoto:

```bash
mysql -h 69.49.241.25 -u apassa73_intranet -p apassa73_intranet < database.sql
```

Ou importe via phpMyAdmin.

### 2. Configurar Conexão

As credenciais já estão configuradas em `config.php`:
- **Host**: 69.49.241.25
- **Usuário**: apassa73_intranet
- **Banco**: apassa73_intranet
- **Senha**: Dema@1973

### 3. Acesso Inicial

**Usuário Administrador Padrão:**
- **Email**: admin@intranet.com
- **Senha**: admin123

⚠️ **IMPORTANTE**: Altere a senha do administrador após o primeiro acesso!

## 📁 Estrutura de Arquivos

```
intranet/
├── config.php              # Configuração do banco de dados
├── functions.php           # Funções auxiliares e controle de acesso
├── index.php              # Página inicial (redireciona)
├── login.php              # Página de login
├── logout.php             # Processo de logout
├── dashboard.php          # Dashboard principal
├── header.php             # Cabeçalho comum
├── styles.css             # Estilos CSS
├── database.sql           # Script de criação do banco de dados
├── README.md              # Este arquivo
└── admin/                 # Painel administrativo
    ├── index.php          # Dashboard admin
    ├── usuarios.php       # Gerenciar usuários
    ├── setores.php        # Gerenciar setores
    ├── permissoes.php     # Gerenciar permissões
    └── logs.php           # Logs do sistema
```

## 🗄️ Estrutura do Banco de Dados

### Tabelas Principais

- **usuarios**: Armazena dados dos usuários do sistema
- **setores**: Departamentos/setores da empresa
- **modulos**: Módulos/funcionalidades do sistema
- **permissoes**: Relacionamento entre setores e módulos com níveis de acesso
- **logs_acesso**: Registro de todas as ações realizadas

## 🔐 Segurança

- Senhas criptografadas com `password_hash()` (bcrypt)
- Proteção contra SQL Injection (prepared statements)
- Sanitização de dados de entrada
- Controle de sessão seguro
- Registro de todas as ações importantes

## 👥 Gerenciamento de Usuários

### Criar Usuário
1. Acesse **Administração > Gerenciar Usuários**
2. Clique em **+ Novo Usuário**
3. Preencha os dados e marque "Administrador" se necessário
4. Salve

### Editar Usuário
1. Na lista de usuários, clique em **Editar**
2. Modifique os dados necessários
3. Para alterar senha, preencha o campo "Senha"
4. Salve as alterações

## 🏢 Gerenciamento de Setores

1. Acesse **Administração > Gerenciar Setores**
2. Cadastre os setores da empresa
3. Vincule usuários aos setores

## 🔐 Configurar Permissões

1. Acesse **Administração > Gerenciar Permissões**
2. Selecione um setor
3. Configure as permissões para cada módulo:
   - **Visualizar**: Pode ver o módulo
   - **Criar**: Pode criar novos registros
   - **Editar**: Pode modificar registros
   - **Excluir**: Pode remover registros
4. Salve as permissões

## 📊 Logs do Sistema

Todos os acessos e ações são registrados automaticamente:
- Login/Logout
- Criação de usuários
- Edição de configurações
- Alterações de permissões

Acesse **Administração > Logs do Sistema** para visualizar.

## 🎨 Personalização

### Modificar Cores
Edite o arquivo `styles.css` e ajuste as cores do gradiente:

```css
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
```

### Adicionar Módulos
1. Insira o novo módulo na tabela `modulos`
2. Configure as permissões em **Administração > Gerenciar Permissões**
3. Implemente a funcionalidade

## 🆘 Suporte

### Problemas Comuns

**Erro de Conexão ao Banco de Dados:**
- Verifique se o servidor MySQL está acessível
- Confirme as credenciais em `config.php`
- Verifique se o IP está liberado no firewall

**Não Consegue Fazer Login:**
- Use as credenciais padrão: admin@intranet.com / admin123
- Verifique se a tabela `usuarios` foi criada corretamente

**Permissões Não Funcionam:**
- Certifique-se de que o usuário está vinculado a um setor
- Verifique se as permissões estão configuradas para o setor

## 📝 Changelog

### Versão 1.0.0
- Sistema completo de autenticação
- Gerenciamento de usuários
- Gerenciamento de setores
- Sistema de permissões granulares
- Logs de acesso e ações
- Interface moderna e responsiva

## 📄 Licença

Este sistema foi desenvolvido para uso interno da empresa.

---

## 🎨 Paleta de Cores

- **Primary (Verde Principal)**: #13ec6a
- **Background Light**: #f6f8f7
- **Background Dark**: #102217
- **Card Dark**: #162b20
- **Border Dark**: #234832

---

**Desenvolvido com ❤️ para gestão eficiente de intranet**
